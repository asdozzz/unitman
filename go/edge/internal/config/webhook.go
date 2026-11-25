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

	RateLimitPerSecond int
}

// LoadWebhook reads the webhook ingress configuration from the environment.
//
// PHP_WEBHOOK_BASE_URL points at the Symfony HTTP worker (for example
// http://web:8080) and is the only required value. Signing secrets are
// optional: an empty secret disables the corresponding provider check.
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
