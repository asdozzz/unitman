package logging

import (
	"io"
	"log/slog"
	"net/http"
	"time"
)

// NewLogger builds a structured JSON logger writing to stderr.
func NewLogger(w io.Writer) *slog.Logger {
	return slog.New(slog.NewJSONHandler(w, &slog.HandlerOptions{Level: slog.LevelInfo}))
}

// Middleware logs method, path, status, duration and byte counts. Request and
// response bodies are never logged, which keeps stepsContent, tokens and the
// HMAC secret out of the access log.
func Middleware(logger *slog.Logger, next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		started := time.Now()

		body := &countingBody{ReadCloser: r.Body}
		r.Body = body

		recorder := &statusRecorder{ResponseWriter: w, status: http.StatusOK}

		next.ServeHTTP(recorder, r)

		logger.Info("http_request",
			"method", r.Method,
			"path", r.URL.Path,
			"status", recorder.status,
			"duration_ms", time.Since(started).Milliseconds(),
			"request_bytes", body.bytes,
			"response_bytes", recorder.bytes,
		)
	})
}

type statusRecorder struct {
	http.ResponseWriter
	status int
	bytes  int
}

func (r *statusRecorder) WriteHeader(status int) {
	r.status = status
	r.ResponseWriter.WriteHeader(status)
}

func (r *statusRecorder) Write(payload []byte) (int, error) {
	written, err := r.ResponseWriter.Write(payload)
	r.bytes += written

	return written, err
}

// Unwrap keeps http.ResponseController and streaming-friendly paths working.
func (r *statusRecorder) Unwrap() http.ResponseWriter {
	return r.ResponseWriter
}

type countingBody struct {
	io.ReadCloser
	bytes int64
}

func (b *countingBody) Read(payload []byte) (int, error) {
	read, err := b.ReadCloser.Read(payload)
	b.bytes += int64(read)

	return read, err
}
