package config

import (
	"fmt"
	"os"
	"strconv"
	"strings"
	"time"

	"unitman/edge/internal/progress"
)

// Config is the full environment-driven configuration of the edge service.
type Config struct {
	ListenAddr string

	// RunnerSecretMasterKey is RUNNER_SECRET_MASTER_KEY with APP_SECRET as
	// fallback, resolved exactly like the PHP RunnerSecretGenerator.
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
	cfg := Config{
		ListenAddr:            getenv("EDGE_LISTEN_ADDR", "0.0.0.0:8081"),
		RunnerSecretMasterKey: resolveRunnerSecretMasterKey(),
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

	var err error

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

// resolveRunnerSecretMasterKey mirrors PHP RunnerSecretGenerator: a blank
// RUNNER_SECRET_MASTER_KEY falls back to APP_SECRET. The result may be blank;
// the progress handler then fails closed with 503.
func resolveRunnerSecretMasterKey() string {
	if masterKey := strings.TrimSpace(os.Getenv("RUNNER_SECRET_MASTER_KEY")); masterKey != "" {
		return masterKey
	}

	return strings.TrimSpace(os.Getenv("APP_SECRET"))
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
