package config

import (
	"fmt"
	"log/slog"
	"os"
	"strconv"
	"strings"
	"time"

	"unitman/edge/internal/progress"
)

// Config is the full environment-driven configuration of the edge service.
type Config struct {
	ListenAddr string

	// RunnerSecretMasterKey is the explicit RUNNER_SECRET_MASTER_KEY. The
	// APP_SECRET fallback is disabled unless the operator opts in with
	// EDGE_ALLOW_RUNNER_SECRET_FROM_APP_SECRET=true in a dev environment.
	RunnerSecretMasterKey string

	RedisAddr     string
	RedisUser     string
	RedisPassword string
	RedisDB       int

	CentrifugoAPIEndpoint string
	CentrifugoAPIKey      string

	MaxStepsContentBytes int
	MaxSteps             int
	MaxStepResponseBytes int
	ProgressTTL          time.Duration
}

// Load reads configuration from process environment with production defaults.
func Load() (Config, error) {
	runnerSecretMasterKey, runnerSecretFromAppSecret, err := resolveRunnerSecretMasterKey()
	if err != nil {
		return Config{}, err
	}
	if runnerSecretFromAppSecret {
		// Name-only warning: never log the secret value itself.
		slog.Default().Warn("runner secret derived from APP_SECRET",
			"secret", "RUNNER_SECRET_MASTER_KEY",
			"app_env", os.Getenv("APP_ENV"),
		)
	}

	cfg := Config{
		ListenAddr:            getenv("EDGE_LISTEN_ADDR", "0.0.0.0:8081"),
		RunnerSecretMasterKey: runnerSecretMasterKey,
		RedisAddr:             getenv("REDIS_ADDR", "redis:6379"),
		RedisUser:             os.Getenv("REDIS_USER"),
		RedisPassword:         os.Getenv("REDIS_PASSWORD"),
		CentrifugoAPIEndpoint: os.Getenv("CENTRIFUGO_API_ENDPOINT"),
		CentrifugoAPIKey:      os.Getenv("CENTRIFUGO_API_KEY"),
		MaxStepsContentBytes:  progress.DefaultMaxStepsContentBytes,
		MaxSteps:              progress.DefaultMaxSteps,
		MaxStepResponseBytes:  progress.DefaultMaxStepResponseBytes,
		ProgressTTL:           7200 * time.Second,
	}

	if cfg.RedisDB, err = intEnv("REDIS_DB", 0); err != nil {
		return Config{}, err
	}

	if cfg.MaxStepsContentBytes, err = positiveIntEnv("MAX_STEPS_CONTENT_BYTES", cfg.MaxStepsContentBytes); err != nil {
		return Config{}, err
	}

	if cfg.MaxSteps, err = positiveIntEnv("MAX_STEPS", cfg.MaxSteps); err != nil {
		return Config{}, err
	}

	if cfg.MaxStepResponseBytes, err = positiveIntEnv("MAX_STEP_RESPONSE_BYTES", cfg.MaxStepResponseBytes); err != nil {
		return Config{}, err
	}

	progressTTL, err := positiveIntEnv("PROGRESS_TTL_SECONDS", 7200)
	if err != nil {
		return Config{}, err
	}
	cfg.ProgressTTL = time.Duration(progressTTL) * time.Second

	if cfg.RedisDB < 0 {
		return Config{}, fmt.Errorf("REDIS_DB must not be negative")
	}

	return cfg, nil
}

// resolveRunnerSecretMasterKey returns the dedicated runner secret. There is no
// implicit APP_SECRET fallback: outside a dev environment a missing key is a
// hard configuration error (fail closed). In dev/development/local/test the
// fallback is still opt-in only, via
// EDGE_ALLOW_RUNNER_SECRET_FROM_APP_SECRET=true, and the caller logs a WARN that
// names the variable without exposing its value.
func resolveRunnerSecretMasterKey() (masterKey string, fromAppSecret bool, err error) {
	if explicit := strings.TrimSpace(os.Getenv("RUNNER_SECRET_MASTER_KEY")); explicit != "" {
		return explicit, false, nil
	}

	allowFallback, err := boolEnv("EDGE_ALLOW_RUNNER_SECRET_FROM_APP_SECRET", false)
	if err != nil {
		return "", false, err
	}

	if !allowFallback {
		return "", false, fmt.Errorf(
			"RUNNER_SECRET_MASTER_KEY is required; set it explicitly " +
				"(openssl rand -hex 32). The APP_SECRET fallback is disabled " +
				"unless EDGE_ALLOW_RUNNER_SECRET_FROM_APP_SECRET=true in a dev environment",
		)
	}

	if !isDevLikeEnv(os.Getenv("APP_ENV")) {
		return "", false, fmt.Errorf(
			"EDGE_ALLOW_RUNNER_SECRET_FROM_APP_SECRET is only allowed when APP_ENV is dev/local/test (got %q)",
			os.Getenv("APP_ENV"),
		)
	}

	appSecret := strings.TrimSpace(os.Getenv("APP_SECRET"))
	if appSecret == "" {
		return "", false, fmt.Errorf(
			"RUNNER_SECRET_MASTER_KEY and APP_SECRET are both empty: cannot derive the runner secret",
		)
	}

	return appSecret, true, nil
}

// isDevLikeEnv reports whether APP_ENV is one of the explicitly supported
// development environments. It matches the webhook escape-hatch gating.
func isDevLikeEnv(appEnv string) bool {
	switch strings.ToLower(strings.TrimSpace(appEnv)) {
	case "dev", "development", "local", "test":
		return true
	default:
		return false
	}
}

func getenv(name, fallback string) string {
	if value := os.Getenv(name); value != "" {
		return value
	}

	return fallback
}

func intEnv(name string, fallback int) (int, error) {
	raw := os.Getenv(name)
	if raw == "" {
		return fallback, nil
	}

	value, err := strconv.Atoi(raw)
	if err != nil {
		return 0, fmt.Errorf("%s must be an integer: %w", name, err)
	}

	return value, nil
}

func positiveIntEnv(name string, fallback int) (int, error) {
	value, err := intEnv(name, fallback)
	if err != nil {
		return 0, err
	}

	if value <= 0 {
		return 0, fmt.Errorf("%s must be a positive integer", name)
	}

	return value, nil
}
