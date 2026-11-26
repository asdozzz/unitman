package config

import (
	"bytes"
	"log/slog"
	"strings"
	"testing"
)

// clearConfigEnv neutralises every variable Load reads so the table tests are
// independent of the host environment.
func clearConfigEnv(t *testing.T) {
	t.Helper()

	for _, name := range []string{
		"EDGE_LISTEN_ADDR",
		"RUNNER_SECRET_MASTER_KEY",
		"APP_SECRET",
		"APP_ENV",
		"EDGE_ALLOW_RUNNER_SECRET_FROM_APP_SECRET",
		"REDIS_ADDR",
		"REDIS_USER",
		"REDIS_PASSWORD",
		"REDIS_DB",
		"CENTRIFUGO_API_ENDPOINT",
		"CENTRIFUGO_API_KEY",
		"MAX_STEPS_CONTENT_BYTES",
		"MAX_STEPS",
		"MAX_STEP_RESPONSE_BYTES",
		"PROGRESS_TTL_SECONDS",
	} {
		t.Setenv(name, "")
	}
}

// captureDefaultLogger redirects the process-wide slog default into a buffer
// for the duration of the test.
func captureDefaultLogger(t *testing.T) *bytes.Buffer {
	t.Helper()

	var buf bytes.Buffer
	previous := slog.Default()
	slog.SetDefault(slog.New(slog.NewTextHandler(&buf, &slog.HandlerOptions{
		Level: slog.LevelDebug,
	})))
	t.Cleanup(func() {
		slog.SetDefault(previous)
	})

	return &buf
}

func TestLoadRunnerSecretTable(t *testing.T) {
	const (
		realMasterKey = "test-runner-master-key-not-for-production"
		appSecret     = "test-app-secret-not-for-production"
	)

	tests := []struct {
		name        string
		env         map[string]string
		wantErr     string
		wantKey     string
		wantWarning bool
	}{
		{
			name:    "explicit master key is used",
			env:     map[string]string{"RUNNER_SECRET_MASTER_KEY": realMasterKey},
			wantKey: realMasterKey,
		},
		{
			name:    "missing in prod fails closed",
			env:     map[string]string{"APP_ENV": "prod", "APP_SECRET": appSecret},
			wantErr: "RUNNER_SECRET_MASTER_KEY is required",
		},
		{
			name: "missing in dev without explicit flag fails closed",
			env: map[string]string{
				"APP_ENV":    "dev",
				"APP_SECRET": appSecret,
			},
			wantErr: "RUNNER_SECRET_MASTER_KEY is required",
		},
		{
			name: "flag outside dev environments is rejected",
			env: map[string]string{
				"APP_ENV":    "prod",
				"APP_SECRET": appSecret,
				"EDGE_ALLOW_RUNNER_SECRET_FROM_APP_SECRET": "true",
			},
			wantErr: "only allowed when APP_ENV is dev/local/test",
		},
		{
			name: "dev with explicit flag derives from APP_SECRET and warns",
			env: map[string]string{
				"APP_ENV":    "test",
				"APP_SECRET": appSecret,
				"EDGE_ALLOW_RUNNER_SECRET_FROM_APP_SECRET": "true",
			},
			wantKey:     appSecret,
			wantWarning: true,
		},
		{
			name: "dev with flag but no APP_SECRET fails closed",
			env: map[string]string{
				"APP_ENV": "local",
				"EDGE_ALLOW_RUNNER_SECRET_FROM_APP_SECRET": "true",
			},
			wantErr: "both empty",
		},
	}

	for _, tc := range tests {
		t.Run(tc.name, func(t *testing.T) {
			clearConfigEnv(t)
			logs := captureDefaultLogger(t)

			for key, value := range tc.env {
				t.Setenv(key, value)
			}

			cfg, err := Load()

			if tc.wantErr != "" {
				if err == nil || !strings.Contains(err.Error(), tc.wantErr) {
					t.Fatalf("Load() error = %v, want substring %q", err, tc.wantErr)
				}
				return
			}

			if err != nil {
				t.Fatalf("Load() unexpected error: %v", err)
			}

			if cfg.RunnerSecretMasterKey != tc.wantKey {
				t.Fatalf("RunnerSecretMasterKey = %q, want %q", cfg.RunnerSecretMasterKey, tc.wantKey)
			}

			got := logs.String()
			if tc.wantWarning {
				if !strings.Contains(got, "RUNNER_SECRET_MASTER_KEY") ||
					!strings.Contains(got, "derived from APP_SECRET") {
					t.Fatalf("expected a name-only warn log, got %q", got)
				}
				if strings.Contains(got, appSecret) {
					t.Fatalf("warn log leaked the secret value: %q", got)
				}
				return
			}

			if strings.TrimSpace(got) != "" {
				t.Fatalf("expected no log output, got %q", got)
			}
		})
	}
}
