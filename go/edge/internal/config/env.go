package config

import (
	"fmt"
	"os"
	"strconv"
	"strings"
)

func firstNonEmpty(values ...string) string {
	for _, value := range values {
		if trimmed := strings.TrimSpace(value); trimmed != "" {
			return trimmed
		}
	}

	return ""
}

func int64Env(name string, fallback int64) (int64, error) {
	raw := strings.TrimSpace(os.Getenv(name))
	if raw == "" {
		return fallback, nil
	}

	value, err := strconv.ParseInt(raw, 10, 64)
	if err != nil {
		return 0, fmt.Errorf("%s must be an integer: %w", name, err)
	}

	return value, nil
}

func nonNegativeIntEnv(name string, fallback int) (int, error) {
	value, err := intEnv(name, fallback)
	if err != nil {
		return 0, err
	}

	if value < 0 {
		return 0, fmt.Errorf("%s must not be negative", name)
	}

	return value, nil
}

func positiveInt64Env(name string, fallback int64) (int64, error) {
	value, err := int64Env(name, fallback)
	if err != nil {
		return 0, err
	}

	if value <= 0 {
		return 0, fmt.Errorf("%s must be a positive integer", name)
	}

	return value, nil
}

func boolEnv(name string, fallback bool) (bool, error) {
	raw := strings.TrimSpace(os.Getenv(name))
	if raw == "" {
		return fallback, nil
	}

	value, err := strconv.ParseBool(raw)
	if err != nil {
		return false, fmt.Errorf("%s must be a boolean: %w", name, err)
	}

	return value, nil
}
