package config

import (
	"os"
	"strings"
)

// TelemetryConfig is the environment-driven configuration of the metrics gateway.
type TelemetryConfig struct {
	ListenAddr   string
	DockerSocket string
	Prefix       string
}

// LoadTelemetry reads the telemetry configuration from the environment.
func LoadTelemetry() TelemetryConfig {
	return TelemetryConfig{
		ListenAddr:   getenv("TELEMETRY_LISTEN_ADDR", "0.0.0.0:8083"),
		DockerSocket: getenv("DOCKER_SOCKET", "/var/run/docker.sock"),
		Prefix:       strings.TrimSpace(os.Getenv("TELEMETRY_PREFIX")),
	}
}
