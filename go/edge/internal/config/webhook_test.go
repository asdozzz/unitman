package config

import (
	"testing"
)

func clearWebhookEnv(t *testing.T) {
	t.Helper()

	for _, name := range []string{
		"WEBHOOK_LISTEN_ADDR",
		"PHP_WEBHOOK_BASE_URL",
		"WEBHOOK_MAX_BYTES",
		"GITLAB_WEBHOOK_SECRET",
		"GITHUB_WEBHOOK_SECRET",
		"WEBHOOK_RATE_LIMIT_PER_SECOND",
	} {
		t.Setenv(name, "")
	}
}

func TestLoadWebhookDefaults(t *testing.T) {
	clearWebhookEnv(t)
	t.Setenv("PHP_WEBHOOK_BASE_URL", "http://web:8080/")

	cfg, err := LoadWebhook()
	if err != nil {
		t.Fatalf("load webhook config: %v", err)
	}

	if cfg.ListenAddr != "0.0.0.0:8084" {
		t.Fatalf("ListenAddr = %q, want 0.0.0.0:8084", cfg.ListenAddr)
	}

	if cfg.PHPBaseURL != "http://web:8080" {
		t.Fatalf("PHPBaseURL = %q, want http://web:8080", cfg.PHPBaseURL)
	}

	if cfg.MaxBytes != DefaultWebhookMaxBytes {
		t.Fatalf("MaxBytes = %d, want %d", cfg.MaxBytes, DefaultWebhookMaxBytes)
	}

	if cfg.RateLimitPerSecond != DefaultWebhookRateLimitPerSecond {
		t.Fatalf("RateLimitPerSecond = %d, want %d", cfg.RateLimitPerSecond, DefaultWebhookRateLimitPerSecond)
	}
}

func TestLoadWebhookRateLimitZeroDisables(t *testing.T) {
	clearWebhookEnv(t)
	t.Setenv("PHP_WEBHOOK_BASE_URL", "http://web:8080")
	t.Setenv("WEBHOOK_RATE_LIMIT_PER_SECOND", "0")

	cfg, err := LoadWebhook()
	if err != nil {
		t.Fatalf("load webhook config: %v", err)
	}

	if cfg.RateLimitPerSecond != 0 {
		t.Fatalf("RateLimitPerSecond = %d, want 0", cfg.RateLimitPerSecond)
	}
}

func TestLoadWebhookRejectsNegativeRateLimit(t *testing.T) {
	clearWebhookEnv(t)
	t.Setenv("PHP_WEBHOOK_BASE_URL", "http://web:8080")
	t.Setenv("WEBHOOK_RATE_LIMIT_PER_SECOND", "-1")

	if _, err := LoadWebhook(); err == nil {
		t.Fatal("expected an error for a negative rate limit")
	}
}

func TestLoadWebhookRequiresPHPBaseURL(t *testing.T) {
	clearWebhookEnv(t)

	if _, err := LoadWebhook(); err == nil {
		t.Fatal("expected an error when PHP_WEBHOOK_BASE_URL is missing")
	}
}
