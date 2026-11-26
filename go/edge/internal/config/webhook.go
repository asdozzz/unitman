package config

import (
	"fmt"
	"net/url"
	"os"
	"strings"
)

// DefaultWebhookMaxBytes is the largest webhook payload accepted at the edge;
// it matches the PHP-side event payload limit.
const DefaultWebhookMaxBytes int64 = 5 << 20

// DefaultWebhookRateLimitPerSecond is the per project/provider token bucket
// refill rate. Zero disables rate limiting.
const DefaultWebhookRateLimitPerSecond = 100

// WebhookConfig is the environment-driven configuration of the webhook ingress.
type WebhookConfig struct {
	ListenAddr string

	PHPBaseURL string

	MaxBytes int64

	GitLabSecret    string
	GitHubSecret    string
	GiteaSecret     string
	ForgejoSecret   string
	GitflicSecret   string
	GitverseSecret  string
	BitbucketSecret string

	// AllowUnsigned disables signature verification for providers without a
	// secret. It is only honored when APP_ENV is dev/local/test and must be
	// requested explicitly via WEBHOOK_ALLOW_UNSIGNED=true.
	AllowUnsigned bool

	RateLimitPerSecond int
}

// hasAnySecret reports whether at least one provider signing secret is set.
func (c WebhookConfig) hasAnySecret() bool {
	return c.GitLabSecret != "" ||
		c.GitHubSecret != "" ||
		c.GiteaSecret != "" ||
		c.ForgejoSecret != "" ||
		c.GitflicSecret != "" ||
		c.GitverseSecret != "" ||
		c.BitbucketSecret != ""
}

// LoadWebhook reads the webhook ingress configuration from the environment.
//
// PHP_WEBHOOK_BASE_URL points at the Symfony HTTP worker (for example
// http://web:8080). At least one provider signing secret is required unless the
// explicit WEBHOOK_ALLOW_UNSIGNED=true development escape hatch is set, which is
// only honored when APP_ENV is dev, development, local or test.
func LoadWebhook() (WebhookConfig, error) {
	cfg := WebhookConfig{
		ListenAddr:         getenv("WEBHOOK_LISTEN_ADDR", "0.0.0.0:8084"),
		PHPBaseURL:         strings.TrimRight(strings.TrimSpace(os.Getenv("PHP_WEBHOOK_BASE_URL")), "/"),
		MaxBytes:           DefaultWebhookMaxBytes,
		GitLabSecret:       os.Getenv("GITLAB_WEBHOOK_SECRET"),
		GitHubSecret:       os.Getenv("GITHUB_WEBHOOK_SECRET"),
		GiteaSecret:        os.Getenv("GITEA_WEBHOOK_SECRET"),
		ForgejoSecret:      os.Getenv("FORGEJO_WEBHOOK_SECRET"),
		GitflicSecret:      os.Getenv("GITFLIC_WEBHOOK_SECRET"),
		GitverseSecret:     os.Getenv("GITVERSE_WEBHOOK_SECRET"),
		BitbucketSecret:    os.Getenv("BITBUCKET_WEBHOOK_SECRET"),
		RateLimitPerSecond: DefaultWebhookRateLimitPerSecond,
	}

	maxBytes, err := positiveInt64Env("WEBHOOK_MAX_BYTES", DefaultWebhookMaxBytes)
	if err != nil {
		return WebhookConfig{}, err
	}
	cfg.MaxBytes = maxBytes

	rateLimit, err := nonNegativeIntEnv("WEBHOOK_RATE_LIMIT_PER_SECOND", DefaultWebhookRateLimitPerSecond)
	if err != nil {
		return WebhookConfig{}, err
	}
	cfg.RateLimitPerSecond = rateLimit

	allowUnsigned, err := boolEnv("WEBHOOK_ALLOW_UNSIGNED", false)
	if err != nil {
		return WebhookConfig{}, err
	}
	cfg.AllowUnsigned = allowUnsigned

	if cfg.AllowUnsigned {
		switch strings.ToLower(strings.TrimSpace(os.Getenv("APP_ENV"))) {
		case "dev", "development", "local", "test":
		default:
			return WebhookConfig{}, fmt.Errorf(
				"WEBHOOK_ALLOW_UNSIGNED is only allowed when APP_ENV is dev/local/test (got %q)",
				os.Getenv("APP_ENV"),
			)
		}
	}

	if !cfg.AllowUnsigned && !cfg.hasAnySecret() {
		return WebhookConfig{}, fmt.Errorf(
			"no webhook signing secret configured: set at least one of " +
				"GITLAB_WEBHOOK_SECRET, GITHUB_WEBHOOK_SECRET, GITEA_WEBHOOK_SECRET, " +
				"FORGEJO_WEBHOOK_SECRET, GITFLIC_WEBHOOK_SECRET, GITVERSE_WEBHOOK_SECRET, " +
				"BITBUCKET_WEBHOOK_SECRET or explicitly set WEBHOOK_ALLOW_UNSIGNED=true in development",
		)
	}

	if cfg.PHPBaseURL == "" {
		return WebhookConfig{}, fmt.Errorf("PHP_WEBHOOK_BASE_URL is required")
	}

	parsed, err := url.Parse(cfg.PHPBaseURL)
	if err != nil {
		return WebhookConfig{}, fmt.Errorf("PHP_WEBHOOK_BASE_URL is invalid: %w", err)
	}

	if parsed.Scheme != "http" && parsed.Scheme != "https" {
		return WebhookConfig{}, fmt.Errorf("PHP_WEBHOOK_BASE_URL must use http or https")
	}

	if parsed.Host == "" {
		return WebhookConfig{}, fmt.Errorf("PHP_WEBHOOK_BASE_URL must include a host")
	}

	return cfg, nil
}
