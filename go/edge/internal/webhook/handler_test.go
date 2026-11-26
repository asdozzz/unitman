package webhook

import (
	"bytes"
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"io"
	"net/http"
	"net/http/httptest"
	"strings"
	"sync"
	"testing"
	"time"
)

type capturedRequest struct {
	Method string
	Path   string
	Header http.Header
	Body   []byte
}

type upstream struct {
	server *httptest.Server

	mu          sync.Mutex
	requests    []capturedRequest
	status      int
	payload     []byte
	contentType string
}

func newUpstream(t *testing.T) *upstream {
	t.Helper()

	fake := &upstream{
		status:      http.StatusOK,
		payload:     []byte(`{"status":"success","data":{"eventId":"evt-1"}}`),
		contentType: "application/json",
	}

	fake.server = httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		body, _ := io.ReadAll(r.Body)

		fake.mu.Lock()
		fake.requests = append(fake.requests, capturedRequest{
			Method: r.Method,
			Path:   r.URL.EscapedPath(),
			Header: r.Header.Clone(),
			Body:   body,
		})
		status, payload, contentType := fake.status, fake.payload, fake.contentType
		fake.mu.Unlock()

		w.Header().Set("Content-Type", contentType)
		w.WriteHeader(status)
		_, _ = w.Write(payload)
	}))

	t.Cleanup(fake.server.Close)

	return fake
}

func (u *upstream) count() int {
	u.mu.Lock()
	defer u.mu.Unlock()

	return len(u.requests)
}

func (u *upstream) last(t *testing.T) capturedRequest {
	t.Helper()

	u.mu.Lock()
	defer u.mu.Unlock()

	if len(u.requests) == 0 {
		t.Fatal("upstream received no request")
	}

	return u.requests[len(u.requests)-1]
}

func newIngress(t *testing.T, options Options) *httptest.Server {
	t.Helper()

	handler, err := NewHandler(options)
	if err != nil {
		t.Fatalf("build handler: %v", err)
	}

	mux := http.NewServeMux()
	mux.HandleFunc("POST /api/project/{id}/hook", handler.Hook)
	mux.HandleFunc("GET /healthz", handler.Healthz)

	server := httptest.NewServer(mux)
	t.Cleanup(server.Close)

	return server
}

func doHook(t *testing.T, server *httptest.Server, projectID string, body []byte, headers map[string]string) (int, []byte) {
	t.Helper()

	request, err := http.NewRequest(http.MethodPost, server.URL+"/api/project/"+projectID+"/hook", bytes.NewReader(body))
	if err != nil {
		t.Fatalf("build request: %v", err)
	}

	for name, value := range headers {
		request.Header.Set(name, value)
	}

	response, err := http.DefaultClient.Do(request)
	if err != nil {
		t.Fatalf("perform request: %v", err)
	}
	defer func() {
		_ = response.Body.Close()
	}()

	payload, err := io.ReadAll(response.Body)
	if err != nil {
		t.Fatalf("read response: %v", err)
	}

	return response.StatusCode, payload
}

func signGitHub(secret string, body []byte) string {
	mac := hmac.New(sha256.New, []byte(secret))
	_, _ = mac.Write(body)

	return "sha256=" + hex.EncodeToString(mac.Sum(nil))
}

func TestGitLabTokenVerification(t *testing.T) {
	tests := []struct {
		name         string
		secret       string
		token        string
		wantStatus   int
		wantUpstream int
	}{
		{name: "valid token", secret: "test", token: "test", wantStatus: http.StatusOK, wantUpstream: 1},
		{name: "invalid token", secret: "test", token: "other", wantStatus: http.StatusForbidden, wantUpstream: 0},
		{name: "missing token", secret: "test", token: "", wantStatus: http.StatusForbidden, wantUpstream: 0},
		{name: "empty secret fails closed", secret: "", token: "", wantStatus: http.StatusForbidden, wantUpstream: 0},
	}

	for _, test := range tests {
		t.Run(test.name, func(t *testing.T) {
			fake := newUpstream(t)
			server := newIngress(t, Options{
				PHPBaseURL:   fake.server.URL,
				GitLabSecret: test.secret,
			})

			headers := map[string]string{
				"Content-Type":   "application/json",
				"X-Gitlab-Event": "Push Hook",
				"X-Gitlab-Token": test.token,
			}

			status, _ := doHook(t, server, "42", []byte(`{"object_kind":"push"}`), headers)

			if status != test.wantStatus {
				t.Fatalf("status = %d, want %d", status, test.wantStatus)
			}

			if got := fake.count(); got != test.wantUpstream {
				t.Fatalf("upstream calls = %d, want %d", got, test.wantUpstream)
			}
		})
	}
}

func TestGitHubSignatureVerification(t *testing.T) {
	body := []byte(`{"ref":"refs/heads/main"}`)

	tests := []struct {
		name         string
		secret       string
		signature    func(secret string, body []byte) string
		wantStatus   int
		wantUpstream int
	}{
		{
			name:         "valid signature",
			secret:       "github-secret",
			signature:    func(secret string, body []byte) string { return signGitHub(secret, body) },
			wantStatus:   http.StatusOK,
			wantUpstream: 1,
		},
		{
			name:         "invalid signature",
			secret:       "github-secret",
			signature:    func(string, []byte) string { return "sha256=deadbeef" },
			wantStatus:   http.StatusForbidden,
			wantUpstream: 0,
		},
		{
			name:         "missing signature",
			secret:       "github-secret",
			signature:    func(string, []byte) string { return "" },
			wantStatus:   http.StatusForbidden,
			wantUpstream: 0,
		},
		{
			name:         "empty secret fails closed",
			secret:       "",
			signature:    func(string, []byte) string { return "" },
			wantStatus:   http.StatusForbidden,
			wantUpstream: 0,
		},
	}

	for _, test := range tests {
		t.Run(test.name, func(t *testing.T) {
			fake := newUpstream(t)
			server := newIngress(t, Options{
				PHPBaseURL:   fake.server.URL,
				GitHubSecret: test.secret,
			})

			headers := map[string]string{
				"Content-Type":   "application/json",
				"X-GitHub-Event": "push",
			}

			if signature := test.signature(test.secret, body); signature != "" {
				headers["X-Hub-Signature-256"] = signature
			}

			status, _ := doHook(t, server, "42", body, headers)

			if status != test.wantStatus {
				t.Fatalf("status = %d, want %d", status, test.wantStatus)
			}

			if got := fake.count(); got != test.wantUpstream {
				t.Fatalf("upstream calls = %d, want %d", got, test.wantUpstream)
			}
		})
	}
}

func TestBodyLimitReturns413(t *testing.T) {
	body := bytes.Repeat([]byte("x"), 32)
	headers := map[string]string{"X-Gitlab-Event": "Push Hook"}

	t.Run("streamed body over limit", func(t *testing.T) {
		fake := newUpstream(t)
		server := newIngress(t, Options{PHPBaseURL: fake.server.URL, MaxBytes: 16})

		status, payload := doHook(t, server, "7", body, headers)

		if status != http.StatusRequestEntityTooLarge {
			t.Fatalf("status = %d, want %d", status, http.StatusRequestEntityTooLarge)
		}

		if !strings.Contains(string(payload), "webhook.body_too_large") {
			t.Fatalf("payload = %s, want body_too_large", payload)
		}

		if got := fake.count(); got != 0 {
			t.Fatalf("upstream calls = %d, want 0", got)
		}
	})

	t.Run("declared content-length over limit", func(t *testing.T) {
		fake := newUpstream(t)

		// A real client refuses to send a body shorter than Content-Length, so
		// drive the handler directly to exercise the early declared-size check.
		handler, err := NewHandler(Options{PHPBaseURL: fake.server.URL, MaxBytes: 16})
		if err != nil {
			t.Fatalf("build handler: %v", err)
		}

		mux := http.NewServeMux()
		mux.HandleFunc("POST /api/project/{id}/hook", handler.Hook)

		request := httptest.NewRequest(http.MethodPost, "/api/project/7/hook", bytes.NewReader(body))
		request.ContentLength = 1024
		request.Header.Set("X-Gitlab-Event", "Push Hook")

		recorder := httptest.NewRecorder()
		mux.ServeHTTP(recorder, request)

		if recorder.Code != http.StatusRequestEntityTooLarge {
			t.Fatalf("status = %d, want %d", recorder.Code, http.StatusRequestEntityTooLarge)
		}

		if got := fake.count(); got != 0 {
			t.Fatalf("upstream calls = %d, want 0", got)
		}
	})
}

func TestUnknownProviderReturns400(t *testing.T) {
	fake := newUpstream(t)
	server := newIngress(t, Options{PHPBaseURL: fake.server.URL})

	status, payload := doHook(t, server, "1", []byte(`{}`), map[string]string{"Content-Type": "application/json"})

	if status != http.StatusBadRequest {
		t.Fatalf("status = %d, want %d", status, http.StatusBadRequest)
	}

	if !strings.Contains(string(payload), "webhook.unknown_provider") {
		t.Fatalf("payload = %s, want unknown_provider", payload)
	}

	if got := fake.count(); got != 0 {
		t.Fatalf("upstream calls = %d, want 0", got)
	}
}

func TestProxyForwardsRequestAndResponse(t *testing.T) {
	fake := newUpstream(t)
	fake.mu.Lock()
	fake.status = http.StatusCreated
	fake.payload = []byte(`{"status":"success","data":{"eventId":"evt-42"}}`)
	fake.mu.Unlock()

	server := newIngress(t, Options{
		PHPBaseURL:   fake.server.URL + "/",
		GitLabSecret: "s3cret",
	})

	body := []byte(`{"object_kind":"push","project":{"id":42}}`)
	headers := map[string]string{
		"Content-Type":   "application/json",
		"User-Agent":     "GitLab/17.4.0",
		"X-Gitlab-Event": "Push Hook",
		"X-Gitlab-Token": "s3cret",
	}

	status, payload := doHook(t, server, "42", body, headers)

	if status != http.StatusCreated {
		t.Fatalf("status = %d, want %d", status, http.StatusCreated)
	}

	if string(payload) != string(fake.payload) {
		t.Fatalf("payload = %s, want %s", payload, fake.payload)
	}

	forwarded := fake.last(t)

	if forwarded.Method != http.MethodPost {
		t.Fatalf("method = %s, want POST", forwarded.Method)
	}

	if forwarded.Path != "/api/project/42/hook" {
		t.Fatalf("path = %s, want /api/project/42/hook", forwarded.Path)
	}

	if !bytes.Equal(forwarded.Body, body) {
		t.Fatalf("body = %s, want %s", forwarded.Body, body)
	}

	for name, want := range map[string]string{
		"Content-Type":   "application/json",
		"User-Agent":     "GitLab/17.4.0",
		"X-Gitlab-Event": "Push Hook",
		"X-Gitlab-Token": "s3cret",
	} {
		if got := forwarded.Header.Get(name); got != want {
			t.Fatalf("header %s = %q, want %q", name, got, want)
		}
	}

	if got := forwarded.Header.Get("X-Forwarded-For"); got == "" {
		t.Fatal("X-Forwarded-For was not forwarded")
	}
}

func TestProxyReturns502WhenPHPIsDown(t *testing.T) {
	fake := newUpstream(t)
	fakeURL := fake.server.URL
	fake.server.Close()

	server := newIngress(t, Options{PHPBaseURL: fakeURL, GitLabSecret: "test"})

	status, payload := doHook(t, server, "5", []byte(`{}`), map[string]string{
		"X-Gitlab-Event": "Push Hook",
		"X-Gitlab-Token": "test",
	})

	if status != http.StatusBadGateway {
		t.Fatalf("status = %d, want %d", status, http.StatusBadGateway)
	}

	if !strings.Contains(string(payload), "webhook.php_unavailable") {
		t.Fatalf("payload = %s, want php_unavailable", payload)
	}
}

func TestRateLimiterRejectsBurstAndCanBeDisabled(t *testing.T) {
	frozen := time.Unix(1_700_000_000, 0)
	clock := func() time.Time { return frozen }

	t.Run("burst over limit returns 429", func(t *testing.T) {
		fake := newUpstream(t)
		server := newIngress(t, Options{
			PHPBaseURL:         fake.server.URL,
			AllowUnsigned:      true,
			RateLimitPerSecond: 2,
			Now:                clock,
		})

		headers := map[string]string{"X-Gitlab-Event": "Push Hook"}

		want := []int{http.StatusOK, http.StatusOK, http.StatusTooManyRequests}
		for index, wantStatus := range want {
			status, _ := doHook(t, server, "9", []byte(`{}`), headers)
			if status != wantStatus {
				t.Fatalf("request %d status = %d, want %d", index, status, wantStatus)
			}
		}

		if got := fake.count(); got != 2 {
			t.Fatalf("upstream calls = %d, want 2", got)
		}
	})

	t.Run("zero disables rate limiting", func(t *testing.T) {
		fake := newUpstream(t)
		server := newIngress(t, Options{
			PHPBaseURL:         fake.server.URL,
			AllowUnsigned:      true,
			RateLimitPerSecond: 0,
			Now:                clock,
		})

		headers := map[string]string{"X-Gitlab-Event": "Push Hook"}

		for index := 0; index < 5; index++ {
			status, _ := doHook(t, server, "9", []byte(`{}`), headers)
			if status != http.StatusOK {
				t.Fatalf("request %d status = %d, want 200", index, status)
			}
		}

		if got := fake.count(); got != 5 {
			t.Fatalf("upstream calls = %d, want 5", got)
		}
	})
}

func TestWebhookSignatureMatrix(t *testing.T) {
	body := []byte(`{"ref":"refs/heads/main"}`)

	tests := []struct {
		name          string
		secret        string
		allowUnsigned bool
		signature     string
		wantStatus    int
		wantUpstream  int
		wantBody      string
	}{
		{
			name:          "no secret and flag off is rejected",
			secret:        "",
			allowUnsigned: false,
			signature:     "",
			wantStatus:    http.StatusForbidden,
			wantUpstream:  0,
			wantBody:      "webhook.signature_not_configured",
		},
		{
			name:          "no secret and explicit flag on is allowed",
			secret:        "",
			allowUnsigned: true,
			signature:     "",
			wantStatus:    http.StatusOK,
			wantUpstream:  1,
		},
		{
			name:          "wrong signature is rejected",
			secret:        "github-secret",
			allowUnsigned: false,
			signature:     signGitHub("a-different-secret", body),
			wantStatus:    http.StatusForbidden,
			wantUpstream:  0,
			wantBody:      "webhook.invalid_signature",
		},
		{
			name:          "correct signature is accepted",
			secret:        "github-secret",
			allowUnsigned: false,
			signature:     signGitHub("github-secret", body),
			wantStatus:    http.StatusOK,
			wantUpstream:  1,
		},
		{
			name:          "malformed signature header is rejected",
			secret:        "github-secret",
			allowUnsigned: false,
			signature:     "sha256=not-hex",
			wantStatus:    http.StatusForbidden,
			wantUpstream:  0,
			wantBody:      "webhook.invalid_signature",
		},
		{
			name:          "malformed empty-encoding signature is rejected",
			secret:        "github-secret",
			allowUnsigned: false,
			signature:     "sha256=",
			wantStatus:    http.StatusForbidden,
			wantUpstream:  0,
			wantBody:      "webhook.invalid_signature",
		},
	}

	for _, test := range tests {
		t.Run(test.name, func(t *testing.T) {
			fake := newUpstream(t)
			server := newIngress(t, Options{
				PHPBaseURL:    fake.server.URL,
				GitHubSecret:  test.secret,
				AllowUnsigned: test.allowUnsigned,
			})

			headers := map[string]string{"X-GitHub-Event": "push"}
			if test.signature != "" {
				headers["X-Hub-Signature-256"] = test.signature
			}

			status, payload := doHook(t, server, "42", body, headers)

			if status != test.wantStatus {
				t.Fatalf("status = %d, want %d", status, test.wantStatus)
			}

			if got := fake.count(); got != test.wantUpstream {
				t.Fatalf("upstream calls = %d, want %d", got, test.wantUpstream)
			}

			if test.wantBody != "" && !strings.Contains(string(payload), test.wantBody) {
				t.Fatalf("payload = %s, want %q", payload, test.wantBody)
			}
		})
	}
}

func TestHealthz(t *testing.T) {
	server := newIngress(t, Options{PHPBaseURL: "http://127.0.0.1:1", GitLabSecret: "test"})

	response, err := http.Get(server.URL + "/healthz")
	if err != nil {
		t.Fatalf("perform request: %v", err)
	}
	defer func() {
		_ = response.Body.Close()
	}()

	payload, _ := io.ReadAll(response.Body)

	if response.StatusCode != http.StatusOK {
		t.Fatalf("status = %d, want 200", response.StatusCode)
	}

	if !strings.Contains(string(payload), `"status":"ok"`) {
		t.Fatalf("payload = %s, want status ok", payload)
	}
}

func TestHealthzReportsUnconfiguredSigning(t *testing.T) {
	server := newIngress(t, Options{PHPBaseURL: "http://127.0.0.1:1"})

	response, err := http.Get(server.URL + "/healthz")
	if err != nil {
		t.Fatalf("perform request: %v", err)
	}
	defer func() {
		_ = response.Body.Close()
	}()

	if response.StatusCode != http.StatusServiceUnavailable {
		t.Fatalf("status = %d, want %d", response.StatusCode, http.StatusServiceUnavailable)
	}
}
