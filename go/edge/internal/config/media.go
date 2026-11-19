package config

import (
	"fmt"
	"os"
	"strings"
)

// DefaultMediaMaxBytes is the 200 MiB upload cap used by Mediateka.
const DefaultMediaMaxBytes int64 = 200 << 20

// MediaConfig is the environment-driven configuration of the media gateway.
type MediaConfig struct {
	ListenAddr string
	MaxBytes   int64

	MinioEndpoint     string
	MinioBucket       string
	MinioRegion       string
	MinioAccessKey    string
	MinioSecretKey    string
	MinioUsePathStyle bool
}

// LoadMedia reads the media gateway configuration from the environment.
//
// MINIO_ACCESS_KEY / MINIO_SECRET_KEY are the canonical names; the local stand
// usually exposes MINIO_ROOT_USER / MINIO_ROOT_PASSWORD, which are accepted as
// fallbacks so the container can reuse the compose environment.
func LoadMedia() (MediaConfig, error) {
	cfg := MediaConfig{
		ListenAddr:        getenv("MEDIA_LISTEN_ADDR", "0.0.0.0:8082"),
		MaxBytes:          DefaultMediaMaxBytes,
		MinioEndpoint:     strings.TrimSpace(os.Getenv("MINIO_ENDPOINT")),
		MinioBucket:       strings.TrimSpace(os.Getenv("MINIO_BUCKET")),
		MinioRegion:       getenv("MINIO_REGION", "us-east-1"),
		MinioAccessKey:    firstNonEmpty(os.Getenv("MINIO_ACCESS_KEY"), os.Getenv("MINIO_ROOT_USER")),
		MinioSecretKey:    firstNonEmpty(os.Getenv("MINIO_SECRET_KEY"), os.Getenv("MINIO_ROOT_PASSWORD")),
		MinioUsePathStyle: true,
	}

	maxBytes, err := positiveInt64Env("MEDIA_MAX_BYTES", DefaultMediaMaxBytes)
	if err != nil {
		return MediaConfig{}, err
	}
	cfg.MaxBytes = maxBytes

	pathStyle, err := boolEnv("MINIO_USE_PATH_STYLE", true)
	if err != nil {
		return MediaConfig{}, err
	}
	cfg.MinioUsePathStyle = pathStyle

	if cfg.MinioEndpoint == "" {
		return MediaConfig{}, fmt.Errorf("MINIO_ENDPOINT is required")
	}

	if cfg.MinioBucket == "" {
		return MediaConfig{}, fmt.Errorf("MINIO_BUCKET is required")
	}

	if cfg.MinioAccessKey == "" || cfg.MinioSecretKey == "" {
		return MediaConfig{}, fmt.Errorf("MINIO_ACCESS_KEY and MINIO_SECRET_KEY are required")
	}

	return cfg, nil
}
