package main

import (
	"context"
	"errors"
	"fmt"
	"log/slog"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	"unitman/edge/internal/centrifugo"
	"unitman/edge/internal/config"
	"unitman/edge/internal/logging"
	"unitman/edge/internal/media"
	"unitman/edge/internal/progress"
	"unitman/edge/internal/redisstore"
	"unitman/edge/internal/s3"
	"unitman/edge/internal/telemetry"
	"unitman/edge/internal/webhook"
)

func main() {
	if len(os.Args) < 2 {
		usage()
		os.Exit(2)
	}

	switch os.Args[1] {
	case "progress":
		if err := runProgress(); err != nil {
			fmt.Fprintln(os.Stderr, "edge progress:", err)
			os.Exit(1)
		}
	case "media":
		if err := runMedia(); err != nil {
			fmt.Fprintln(os.Stderr, "edge media:", err)
			os.Exit(1)
		}
	case "telemetry":
		if err := runTelemetry(); err != nil {
			fmt.Fprintln(os.Stderr, "edge telemetry:", err)
			os.Exit(1)
		}
	case "webhook":
		if err := runWebhook(); err != nil {
			fmt.Fprintln(os.Stderr, "edge webhook:", err)
			os.Exit(1)
		}
	case "help", "-h", "--help":
		usage()
	default:
		fmt.Fprintf(os.Stderr, "unknown subcommand %q\n", os.Args[1])
		usage()
		os.Exit(2)
	}
}

func usage() {
	fmt.Fprintln(os.Stderr, "usage: edge <command>")
	fmt.Fprintln(os.Stderr, "")
	fmt.Fprintln(os.Stderr, "commands:")
	fmt.Fprintln(os.Stderr, "  progress   run the runner progress gateway")
	fmt.Fprintln(os.Stderr, "  media      run the MinIO/S3 streaming media gateway")
	fmt.Fprintln(os.Stderr, "  telemetry  run the Docker container metrics exporter")
	fmt.Fprintln(os.Stderr, "  webhook    run the Git provider webhook ingress")
}

func runProgress() error {
	cfg, err := config.Load()
	if err != nil {
		return fmt.Errorf("load config: %w", err)
	}

	logger := logging.NewLogger(os.Stderr)

	store := redisstore.New(redisstore.Options{
		Addr:     cfg.RedisAddr,
		Username: cfg.RedisUser,
		Password: cfg.RedisPassword,
		DB:       cfg.RedisDB,
	})
	defer func() {
		if closeErr := store.Close(); closeErr != nil {
			logger.Warn("redis close failed", "error", closeErr)
		}
	}()

	pingContext, cancelPing := context.WithTimeout(context.Background(), 3*time.Second)
	if pingErr := store.Ping(pingContext); pingErr != nil {
		// Starting without Redis keeps /healthz available for orchestration;
		// individual requests will surface the real storage error.
		logger.Warn("redis ping failed", "error", pingErr)
	}
	cancelPing()

	publisher := centrifugo.New(centrifugo.Options{
		Endpoint: cfg.CentrifugoAPIEndpoint,
		APIKey:   cfg.CentrifugoAPIKey,
	})

	handler := progress.NewHandler(store, publisher, progress.Options{
		Secret: cfg.RunnerCallbackSecret,
		Limits: progress.Limits{
			MaxContentBytes:  cfg.MaxStepsContentBytes,
			MaxSteps:         cfg.MaxSteps,
			MaxResponseBytes: cfg.MaxStepResponseBytes,
		},
		ProgressTTL: cfg.ProgressTTL,
		Logger:      logger,
	})

	mux := http.NewServeMux()
	mux.HandleFunc("POST /api/runner/saveSteps", handler.SaveSteps)
	mux.HandleFunc("GET /api/runner/progress/{responseId}", handler.GetProgress)
	mux.HandleFunc("GET /healthz", handler.Healthz)

	server := &http.Server{
		Addr:              cfg.ListenAddr,
		Handler:           logging.Middleware(logger, mux),
		ReadHeaderTimeout: 10 * time.Second,
		IdleTimeout:       120 * time.Second,
	}

	logger.Info("edge progress gateway listening",
		"addr", cfg.ListenAddr,
		"runner_signature_enabled", cfg.RunnerCallbackSecret != "",
		"centrifugo_configured", cfg.CentrifugoAPIEndpoint != "",
	)

	return serve(logger, server, "shutting down edge progress gateway")
}

func runMedia() error {
	cfg, err := config.LoadMedia()
	if err != nil {
		return fmt.Errorf("load media config: %w", err)
	}

	logger := logging.NewLogger(os.Stderr)

	store, err := s3.New(s3.Options{
		Endpoint:     cfg.MinioEndpoint,
		Bucket:       cfg.MinioBucket,
		Region:       cfg.MinioRegion,
		AccessKey:    cfg.MinioAccessKey,
		SecretKey:    cfg.MinioSecretKey,
		UsePathStyle: cfg.MinioUsePathStyle,
	})
	if err != nil {
		return fmt.Errorf("build s3 client: %w", err)
	}

	handler := media.NewHandler(store, media.Options{
		MaxBytes: cfg.MaxBytes,
		Logger:   logger,
	})

	mux := http.NewServeMux()
	mux.HandleFunc("PUT /media/{key...}", handler.Put)
	mux.HandleFunc("GET /media/{key...}", handler.Get)
	mux.HandleFunc("DELETE /media/{key...}", handler.Delete)
	mux.HandleFunc("GET /healthz", handler.Healthz)

	server := &http.Server{
		Addr:              cfg.ListenAddr,
		Handler:           logging.Middleware(logger, mux),
		ReadHeaderTimeout: 10 * time.Second,
		IdleTimeout:       120 * time.Second,
		// No ReadTimeout/WriteTimeout: uploads and downloads may legitimately
		// take longer than a fixed deadline, the client context bounds them.
	}

	logger.Info("edge media gateway listening",
		"addr", cfg.ListenAddr,
		"minio_endpoint", cfg.MinioEndpoint,
		"minio_bucket", cfg.MinioBucket,
		"minio_region", cfg.MinioRegion,
		"path_style", cfg.MinioUsePathStyle,
		"max_bytes", cfg.MaxBytes,
	)

	return serve(logger, server, "shutting down edge media gateway")
}

func runWebhook() error {
	cfg, err := config.LoadWebhook()
	if err != nil {
		return fmt.Errorf("load webhook config: %w", err)
	}

	logger := logging.NewLogger(os.Stderr)

	handler, err := webhook.NewHandler(webhook.Options{
		PHPBaseURL:         cfg.PHPBaseURL,
		MaxBytes:           cfg.MaxBytes,
		GitLabSecret:       cfg.GitLabSecret,
		GitHubSecret:       cfg.GitHubSecret,
		RateLimitPerSecond: cfg.RateLimitPerSecond,
		Logger:             logger,
	})
	if err != nil {
		return fmt.Errorf("build webhook handler: %w", err)
	}

	mux := http.NewServeMux()
	mux.HandleFunc("POST /api/project/{id}/hook", handler.Hook)
	mux.HandleFunc("GET /healthz", handler.Healthz)

	server := &http.Server{
		Addr:              cfg.ListenAddr,
		Handler:           logging.Middleware(logger, mux),
		ReadHeaderTimeout: 10 * time.Second,
		// The proxy client caps the PHP round trip at 10s; give it headroom.
		WriteTimeout: 30 * time.Second,
		IdleTimeout:  120 * time.Second,
	}

	logger.Info("edge webhook ingress listening",
		"addr", cfg.ListenAddr,
		"php_base_url", cfg.PHPBaseURL,
		"max_bytes", cfg.MaxBytes,
		"gitlab_secret_enabled", cfg.GitLabSecret != "",
		"github_secret_enabled", cfg.GitHubSecret != "",
		"rate_limit_per_second", cfg.RateLimitPerSecond,
	)

	return serve(logger, server, "shutting down edge webhook ingress")
}

func runTelemetry() error {
	cfg := config.LoadTelemetry()

	logger := logging.NewLogger(os.Stderr)
	client := telemetry.NewUnixClient(cfg.DockerSocket)

	handler := telemetry.NewHandler(client, telemetry.Options{
		Prefix: cfg.Prefix,
		Logger: logger,
	})

	mux := http.NewServeMux()
	mux.HandleFunc("GET /metrics", handler.Metrics)
	mux.HandleFunc("GET /healthz", handler.Healthz)

	server := &http.Server{
		Addr:              cfg.ListenAddr,
		Handler:           logging.Middleware(logger, mux),
		ReadHeaderTimeout: 10 * time.Second,
		// The first Docker stats scrape can be slow on a busy host; the body
		// must not be cut off mid-write (Prometheus would see an empty reply).
		WriteTimeout: 60 * time.Second,
		IdleTimeout:  120 * time.Second,
	}

	prefix := cfg.Prefix
	if prefix == "" {
		prefix = "(all)"
	}

	logger.Info("edge telemetry gateway listening",
		"addr", cfg.ListenAddr,
		"docker_socket", cfg.DockerSocket,
		"container_prefix", prefix,
	)

	return serve(logger, server, "shutting down edge telemetry gateway")
}

// serve runs the HTTP server until SIGINT/SIGTERM, then drains it gracefully.
func serve(logger *slog.Logger, server *http.Server, shutdownMessage string) error {
	serverErrors := make(chan error, 1)
	go func() {
		serverErrors <- server.ListenAndServe()
	}()

	shutdownContext, stop := signal.NotifyContext(context.Background(), syscall.SIGINT, syscall.SIGTERM)
	defer stop()

	select {
	case serveErr := <-serverErrors:
		if serveErr != nil && !errors.Is(serveErr, http.ErrServerClosed) {
			return serveErr
		}

		return nil
	case <-shutdownContext.Done():
		logger.Info(shutdownMessage)

		contextWithTimeout, cancel := context.WithTimeout(context.Background(), 10*time.Second)
		defer cancel()

		return server.Shutdown(contextWithTimeout)
	}
}
