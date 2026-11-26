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
		"GITEA_WEBHOOK_SECRET",
		"FORGEJO_WEBHOOK_SECRET",
		"GITFLIC_WEBHOOK_SECRET",
		"GITVERSE_WEBHOOK_SECRET",
		"BITBUCKET_WEBHOOK_SECRET",
		"WEBHOOK_RATE_LIMIT_PER_SECOND",
		"WEBHOOK_ALLOW_UNSIGNED",
		"APP_ENV",
	} {
		t.Setenv(name, "")
	}
}

func TestLoadWebhookDefaults(t *testing.T) {
	clearWebhookEnv(t)
	t.Setenv("PHP_WEBHOOK_BASE_URL", "http://web:8080/")
	t.Setenv("GITLAB_WEBHOOK_SECRET", "test-secret")

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

	if cfg.AllowUnsigned {
		t.Fatal("AllowUnsigned = true, want false by default")
	}
}

func TestLoadWebhookRateLimitZeroDisables(t *testing.T) {
	clearWebhookEnv(t)
	t.Setenv("PHP_WEBHOOK_BASE_URL", "http://web:8080")
	t.Setenv("GITLAB_WEBHOOK_SECRET", "test-secret")
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
	t.Setenv("GITLAB_WEBHOOK_SECRET", "test-secret")
	t.Setenv("WEBHOOK_RATE_LIMIT_PER_SECOND", "-1")

	if _, err := LoadWebhook(); err == nil {
		t.Fatal("expected an error for a negative rate limit")
	}
}

func TestLoadWebhookRequiresPHPBaseURL(t *testing.T) {
	clearWebhookEnv(t)
	t.Setenv("GITLAB_WEBHOOK_SECRET", "test-secret")

	if _, err := LoadWebhook(); err == nil {
		t.Fatal("expected an error when PHP_WEBHOOK_BASE_URL is missing")
	}
}

func TestLoadWebhookRequiresConfiguredSecret(t *testing.T) {
	clearWebhookEnv(t)
	t.Setenv("PHP_WEBHOOK_BASE_URL", "http://web:8080")

	if _, err := LoadWebhook(); err == nil {
		t.Fatal("expected an error when no signing secret is configured")
	}
}

func TestLoadWebhookAllowsUnsignedOnlyInDev(t *testing.T) {
	clearWebhookEnv(t)
	t.Setenv("PHP_WEBHOOK_BASE_URL", "http://web:8080")
	t.Setenv("APP_ENV", "dev")
	t.Setenv("WEBHOOK_ALLOW_UNSIGNED", "true")

	cfg, err := LoadWebhook()
	if err != nil {
		t.Fatalf("load webhook config: %v", err)
	}

	if !cfg.AllowUnsigned {
		t.Fatal("AllowUnsigned = false, want true")
	}
}

func TestLoadWebhookRejectsUnsignedOutsideDev(t *testing.T) {
	clearWebhookEnv(t)
	t.Setenv("PHP_WEBHOOK_BASE_URL", "http://web:8080")
	t.Setenv("APP_ENV", "prod")
	t.Setenv("WEBHOOK_ALLOW_UNSIGNED", "true")

	if _, err := LoadWebhook(); err == nil {
		t.Fatal("expected an error when WEBHOOK_ALLOW_UNSIGNED is enabled outside dev")
	}
}
