package webhook

import (
	"bytes"
	"context"
	"crypto/hmac"
	"crypto/sha256"
	"crypto/subtle"
	"encoding/hex"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"log/slog"
	"net"
	"net/http"
	"net/url"
	"strings"
	"time"
)

const (
	// DefaultMaxBytes mirrors the PHP-side 5 MiB event payload limit.
	DefaultMaxBytes int64 = 5 << 20
	// DefaultRateLimitPerSecond is the per project/provider token bucket rate.
	DefaultRateLimitPerSecond = 100

	// Supported webhook ingress sources.
	ProviderGitLab    = "gitlab"
	ProviderGitHub    = "github"
	ProviderGitea     = "gitea"
	ProviderForgejo   = "forgejo"
	ProviderGitflic   = "gitflic"
	ProviderGitverse  = "gitverse"
	ProviderBitbucket = "bitbucket"

	gitHubSignaturePrefix = "sha256="

	// maxResponseBytes bounds the PHP response copied back to the client; the
	// PHP webhook contract returns a small JSON envelope.
	maxResponseBytes = 1 << 20

	defaultClientTimeout = 10 * time.Second
)

// Options configures the webhook ingress.
type Options struct {
	// PHPBaseURL is the Symfony origin, for example http://web:8080.
	PHPBaseURL string
	// MaxBytes is the largest accepted request body; <= 0 uses DefaultMaxBytes.
	MaxBytes int64
	// GitLabSecret enables the X-Gitlab-Token check when non-empty.
	GitLabSecret string
	// GitHubSecret enables the X-Hub-Signature-256 check when non-empty.
	GitHubSecret string
	// GiteaSecret enables the X-Gitea-Signature check when non-empty.
	GiteaSecret string
	// ForgejoSecret enables the X-Forgejo-Signature check when non-empty.
	ForgejoSecret string
	// GitflicSecret enables the GitFlic signature or token check when non-empty.
	GitflicSecret string
	// GitverseSecret enables the GitVerse Authorization check when non-empty.
	GitverseSecret string
	// BitbucketSecret enables the X-Hub-Signature check when non-empty.
	BitbucketSecret string
	// RateLimitPerSecond is the per project/provider token bucket rate;
	// 0 disables rate limiting.
	RateLimitPerSecond int
	// Client proxies requests to PHP; nil builds a tuned default client.
	Client *http.Client
	// Logger receives structured request logs; nil uses slog.Default().
	Logger *slog.Logger
	// Now is the limiter clock; nil uses time.Now.
	Now func() time.Time
}

// Handler implements POST /api/project/{id}/hook and GET /healthz.
type Handler struct {
	phpBaseURL      string
	maxBytes        int64
	gitLabSecret    string
	gitHubSecret    string
	giteaSecret     string
	forgejoSecret   string
	gitflicSecret   string
	gitverseSecret  string
	bitbucketSecret string
	client          *http.Client
	limiter         *RateLimiter
	logger          *slog.Logger
}

// NewHandler validates options and wires the ingress.
func NewHandler(options Options) (*Handler, error) {
	baseURL := strings.TrimRight(strings.TrimSpace(options.PHPBaseURL), "/")
	parsed, err := url.Parse(baseURL)
	if err != nil {
		return nil, fmt.Errorf("parse PHP base URL: %w", err)
	}

	if parsed.Scheme != "http" && parsed.Scheme != "https" || parsed.Host == "" {
		return nil, fmt.Errorf("PHP base URL must be an absolute http(s) URL")
	}

	maxBytes := options.MaxBytes
	if maxBytes <= 0 {
		maxBytes = DefaultMaxBytes
	}

	client := options.Client
	if client == nil {
		client = newProxyClient()
	}

	logger := options.Logger
	if logger == nil {
		logger = slog.Default()
	}

	return &Handler{
		phpBaseURL:      baseURL,
		maxBytes:        maxBytes,
		gitLabSecret:    options.GitLabSecret,
		gitHubSecret:    options.GitHubSecret,
		giteaSecret:     options.GiteaSecret,
		forgejoSecret:   options.ForgejoSecret,
		gitflicSecret:   options.GitflicSecret,
		gitverseSecret:  options.GitverseSecret,
		bitbucketSecret: options.BitbucketSecret,
		client:          client,
		limiter:         NewRateLimiter(options.RateLimitPerSecond, options.Now),
		logger:          logger,
	}, nil
}

// requestResult carries the values needed for the single structured log line.
type requestResult struct {
	status    int
	provider  string
	bodyBytes int
}

// Hook handles POST /api/project/{id}/hook.
//
// The request is validated and bounded at the edge, then forwarded verbatim to
// the existing PHP endpoint so the PHP contract and event store are unchanged.
func (h *Handler) Hook(w http.ResponseWriter, r *http.Request) {
	started := time.Now()
	result := h.serve(w, r)

	h.logger.Info("webhook_request",
		"method", r.Method,
		"path", r.URL.Path,
		"provider", result.provider,
		"status", result.status,
		"duration_ms", time.Since(started).Milliseconds(),
		"body_bytes", result.bodyBytes,
	)
}

func (h *Handler) serve(w http.ResponseWriter, r *http.Request) requestResult {
	provider, ok := detectProvider(r.Header)
	if !ok {
		writeError(w, http.StatusBadRequest, "webhook.unknown_provider")
		return requestResult{status: http.StatusBadRequest}
	}

	projectID := r.PathValue("id")
	if projectID == "" {
		writeError(w, http.StatusBadRequest, "webhook.missing_project_id")
		return requestResult{status: http.StatusBadRequest, provider: provider}
	}

	if !h.limiter.Allow(projectID + "|" + provider) {
		writeError(w, http.StatusTooManyRequests, "webhook.rate_limited")
		return requestResult{status: http.StatusTooManyRequests, provider: provider}
	}

	// Reject an oversized declared body before touching the stream; the
	// MaxBytesReader below still bounds chunked uploads without Content-Length.
	if r.ContentLength > h.maxBytes {
		writeError(w, http.StatusRequestEntityTooLarge, "webhook.body_too_large")
		return requestResult{status: http.StatusRequestEntityTooLarge, provider: provider}
	}

	raw, err := io.ReadAll(http.MaxBytesReader(w, r.Body, h.maxBytes))
	if err != nil {
		var maxBytesError *http.MaxBytesError
		if errors.As(err, &maxBytesError) {
			writeError(w, http.StatusRequestEntityTooLarge, "webhook.body_too_large")
			return requestResult{status: http.StatusRequestEntityTooLarge, provider: provider}
		}

		writeError(w, http.StatusBadRequest, "webhook.body_unreadable")
		return requestResult{status: http.StatusBadRequest, provider: provider}
	}

	bodyBytes := len(raw)

	if !h.verify(provider, r.Header, raw) {
		writeError(w, http.StatusForbidden, "webhook.invalid_signature")
		return requestResult{status: http.StatusForbidden, provider: provider, bodyBytes: bodyBytes}
	}

	status, payload, err := h.forward(r.Context(), projectID, raw, r)
	if err != nil {
		// PHP is unreachable or timed out; the payload was not persisted and
		// the provider may retry, so surface a retryable gateway error.
		h.logger.Warn("webhook proxy failed",
			"provider", provider,
			"project_id", projectID,
			"error", err,
		)
		writeError(w, http.StatusBadGateway, "webhook.php_unavailable")
		return requestResult{status: http.StatusBadGateway, provider: provider, bodyBytes: bodyBytes}
	}

	if status <= 0 {
		status = http.StatusOK
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	_, _ = w.Write(payload)

	return requestResult{status: status, provider: provider, bodyBytes: bodyBytes}
}

// Healthz handles GET /healthz.
func (h *Handler) Healthz(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	_, _ = w.Write([]byte(`{"status":"ok"}`))
}

// verify applies the provider-specific check; an empty secret disables it.
func (h *Handler) verify(provider string, header http.Header, body []byte) bool {
	switch provider {
	case ProviderGitLab:
		return verifyGitLabToken(h.gitLabSecret, header.Get("X-Gitlab-Token"))
	case ProviderGitHub:
		return verifyGitHubSignature(h.gitHubSecret, body, header.Get("X-Hub-Signature-256"))
	case ProviderGitea:
		return verifyHexSignature(h.giteaSecret, body, header.Get("X-Gitea-Signature"))
	case ProviderForgejo:
		return verifyHexSignature(h.forgejoSecret, body, header.Get("X-Forgejo-Signature"))
	case ProviderGitflic:
		return verifyGitflicSecret(
			h.gitflicSecret,
			body,
			header.Get("X-Gitflic-Signature"),
			header.Get("X-Gitflic-Token"),
		)
	case ProviderGitverse:
		return verifySharedSecret(
			h.gitverseSecret,
			body,
			header.Get("X-Gitverse-Signature"),
			header.Get("Authorization"),
		)
	case ProviderBitbucket:
		return verifyBitbucketSignature(
			h.bitbucketSecret,
			body,
			header.Get("X-Hub-Signature"),
		)
	default:
		return false
	}
}

// detectProvider derives the provider from provider-specific headers. Gitea and
// Forgejo are checked first because their header names are unique to them.
func detectProvider(header http.Header) (string, bool) {
	if header.Get("X-Forgejo-Event") != "" || header.Get("X-Forgejo-Signature") != "" {
		return ProviderForgejo, true
	}

	if header.Get("X-Gitflic-Event") != "" ||
		header.Get("X-Gitflic-Signature") != "" ||
		header.Get("X-Gitflic-Token") != "" {
		return ProviderGitflic, true
	}

	if header.Get("X-Gitverse-Event") != "" ||
		header.Get("X-Gitverse-Signature") != "" ||
		header.Get("X-Gitverse-Token") != "" {
		return ProviderGitverse, true
	}

	// GitVerse настраивает custom webhook с заголовком Authorization и не
	// присылает отдельного заголовка провайдера. Проверяем его последним,
	// чтобы не перехватить запросы остальных провайдеров.
	if strings.HasPrefix(header.Get("Authorization"), "Bearer ") {
		return ProviderGitverse, true
	}

	if header.Get("X-Gitea-Event") != "" || header.Get("X-Gitea-Signature") != "" {
		return ProviderGitea, true
	}

	if header.Get("X-Event-Key") != "" || header.Get("X-Hub-Signature") != "" {
		return ProviderBitbucket, true
	}

	if header.Get("X-Gitlab-Event") != "" || header.Get("X-Gitlab-Token") != "" {
		return ProviderGitLab, true
	}

	if header.Get("X-GitHub-Event") != "" || header.Get("X-Hub-Signature-256") != "" {
		return ProviderGitHub, true
	}

	return "", false
}

// verifyGitLabToken compares the shared token in constant time. An empty
// secret disables the check; a missing token never passes when a secret is set.
func verifyGitLabToken(secret, token string) bool {
	if secret == "" {
		return true
	}

	if token == "" {
		return false
	}

	return subtle.ConstantTimeCompare([]byte(token), []byte(secret)) == 1
}

// verifyGitHubSignature checks X-Hub-Signature-256 = "sha256=" + hex HMAC.
func verifyGitHubSignature(secret string, body []byte, signature string) bool {
	if secret == "" {
		return true
	}

	if !strings.HasPrefix(signature, gitHubSignaturePrefix) {
		return false
	}

	provided, err := hex.DecodeString(signature[len(gitHubSignaturePrefix):])
	if err != nil {
		return false
	}

	mac := hmac.New(sha256.New, []byte(secret))
	_, _ = mac.Write(body)

	return hmac.Equal(mac.Sum(nil), provided)
}

// verifyHexSignature checks an unprefixed hex HMAC-SHA256 signature used by
// Gitea and Forgejo. An empty secret disables the check.
func verifyHexSignature(secret string, body []byte, signature string) bool {
	if secret == "" {
		return true
	}

	if signature == "" {
		return false
	}

	provided, err := hex.DecodeString(strings.TrimSpace(signature))
	if err != nil {
		return false
	}

	mac := hmac.New(sha256.New, []byte(secret))
	_, _ = mac.Write(body)

	return hmac.Equal(mac.Sum(nil), provided)
}

// verifyGitflicSecret accepts either a hex HMAC signature or a shared token
// header. GitFlic documents a webhook secret field but versions differ in how
// they send it, so both are supported.
func verifyGitflicSecret(secret string, body []byte, signature, token string) bool {
	if secret == "" {
		return true
	}

	if signature != "" {
		return verifyHexSignature(secret, body, signature)
	}

	if token == "" {
		return false
	}

	return subtle.ConstantTimeCompare([]byte(token), []byte(secret)) == 1
}

// verifySharedSecret accepts either a hex HMAC signature or a shared secret in
// the Authorization header. GitVerse uses the latter for custom webhooks.
func verifySharedSecret(secret string, body []byte, signature, authorization string) bool {
	if secret == "" {
		return true
	}

	if signature != "" {
		return verifyHexSignature(secret, body, signature)
	}

	token := strings.TrimSpace(strings.TrimPrefix(authorization, "Bearer "))
	if token == "" {
		return false
	}

	return subtle.ConstantTimeCompare([]byte(token), []byte(secret)) == 1
}

// verifyBitbucketSignature checks X-Hub-Signature = "sha256=" + hex HMAC.
func verifyBitbucketSignature(secret string, body []byte, signature string) bool {
	if secret == "" {
		return true
	}

	if !strings.HasPrefix(signature, gitHubSignaturePrefix) {
		return false
	}

	provided, err := hex.DecodeString(signature[len(gitHubSignaturePrefix):])
	if err != nil {
		return false
	}

	mac := hmac.New(sha256.New, []byte(secret))
	_, _ = mac.Write(body)

	return hmac.Equal(mac.Sum(nil), provided)
}

// forward proxies the raw body to the PHP endpoint and returns its status and
// body. Response hop-by-hop headers are dropped, everything else is copied.
func (h *Handler) forward(ctx context.Context, projectID string, body []byte, incoming *http.Request) (int, []byte, error) {
	endpoint := h.phpBaseURL + "/api/project/" + url.PathEscape(projectID) + "/hook"

	request, err := http.NewRequestWithContext(ctx, http.MethodPost, endpoint, bytes.NewReader(body))
	if err != nil {
		return 0, nil, fmt.Errorf("build proxy request: %w", err)
	}

	copyProxyHeaders(request.Header, incoming.Header)
	request.Header.Set("X-Forwarded-For", forwardedFor(incoming))

	response, err := h.client.Do(request)
	if err != nil {
		return 0, nil, err
	}
	defer func() {
		_ = response.Body.Close()
	}()

	payload, err := io.ReadAll(io.LimitReader(response.Body, maxResponseBytes))
	if err != nil {
		return 0, nil, fmt.Errorf("read proxy response: %w", err)
	}

	return response.StatusCode, payload, nil
}

// copyProxyHeaders forwards only the headers the PHP webhook relies on. The
// raw body is never logged, so secrets stay out of the access log.
func copyProxyHeaders(target, source http.Header) {
	for _, name := range []string{"Content-Type", "User-Agent"} {
		if value := source.Get(name); value != "" {
			target.Set(name, value)
		}
	}

	for name, values := range source {
		lower := strings.ToLower(name)

		if lower == "authorization" {
			for _, value := range values {
				if strings.HasPrefix(value, "Bearer ") {
					target.Add(name, value)
				}
			}
			continue
		}

		if !strings.HasPrefix(lower, "x-gitlab-") &&
			!strings.HasPrefix(lower, "x-github-") &&
			!strings.HasPrefix(lower, "x-gitea-") &&
			!strings.HasPrefix(lower, "x-forgejo-") &&
			!strings.HasPrefix(lower, "x-gitflic-") &&
			!strings.HasPrefix(lower, "x-gitverse-") &&
			lower != "x-event-key" &&
			lower != "x-hub-signature" &&
			lower != "x-hub-signature-256" {
			continue
		}

		for _, value := range values {
			target.Add(name, value)
		}
	}
}

// forwardedFor preserves an upstream nginx chain and appends the direct peer.
func forwardedFor(r *http.Request) string {
	peer := r.RemoteAddr
	if host, _, err := net.SplitHostPort(peer); err == nil {
		peer = host
	}

	if existing := strings.TrimSpace(r.Header.Get("X-Forwarded-For")); existing != "" {
		if peer == "" {
			return existing
		}

		return existing + ", " + peer
	}

	return peer
}

// newProxyClient bounds the PHP round trip and keeps connections warm.
func newProxyClient() *http.Client {
	transport := &http.Transport{
		DialContext: (&net.Dialer{
			Timeout:   3 * time.Second,
			KeepAlive: 30 * time.Second,
		}).DialContext,
		MaxIdleConns:          100,
		MaxIdleConnsPerHost:   16,
		IdleConnTimeout:       90 * time.Second,
		ResponseHeaderTimeout: defaultClientTimeout,
		ExpectContinueTimeout: time.Second,
	}

	return &http.Client{
		Timeout:   defaultClientTimeout,
		Transport: transport,
	}
}

type errorEnvelope struct {
	Status  string `json:"status"`
	Message string `json:"message"`
}

func writeError(w http.ResponseWriter, status int, message string) {
	payload, err := json.Marshal(errorEnvelope{Status: "error", Message: message})
	if err != nil {
		payload = []byte(`{"status":"error","message":"webhook.internal_error"}`)
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	_, _ = w.Write(payload)
}
