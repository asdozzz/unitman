package media

import (
	"bytes"
	"context"
	"encoding/json"
	"errors"
	"io"
	"log/slog"
	"net/http"
	"strconv"
	"strings"
)

const (
	// DefaultMaxBytes caps a single upload at 200 MiB, matching the Mediateka
	// file contract (see .docker/php/settings.ini).
	DefaultMaxBytes int64 = 200 << 20

	maxKeyLength = 1024

	zipContentType = "application/zip"
)

var (
	zipLocalFileHeader       = []byte{'P', 'K', 0x03, 0x04}
	zipEndOfCentralDirectory = []byte{'P', 'K', 0x05, 0x06}
)

// ObjectStore is the storage contract used by the media handler. It is
// satisfied by *s3.Client and by test doubles.
type ObjectStore interface {
	Put(ctx context.Context, key string, body io.Reader, size int64, contentType string) error
	Get(ctx context.Context, key string) (*http.Response, error)
	Delete(ctx context.Context, key string) (int, error)
}

// Options configures the media gateway.
type Options struct {
	MaxBytes int64
	Logger   *slog.Logger
}

// Handler implements streamed PUT/GET/DELETE of zip archives in object storage.
type Handler struct {
	store    ObjectStore
	maxBytes int64
	logger   *slog.Logger
}

// NewHandler wires the media gateway with safe defaults.
func NewHandler(store ObjectStore, options Options) *Handler {
	maxBytes := options.MaxBytes
	if maxBytes <= 0 {
		maxBytes = DefaultMaxBytes
	}

	logger := options.Logger
	if logger == nil {
		logger = slog.Default()
	}

	return &Handler{store: store, maxBytes: maxBytes, logger: logger}
}

// Put handles PUT /media/{key...}.
//
// The body is never buffered: the first four bytes are inspected for a zip
// signature, then prefixed back through io.MultiReader while the rest streams
// straight to S3 with UNSIGNED-PAYLOAD.
func (h *Handler) Put(w http.ResponseWriter, r *http.Request) {
	key, ok := keyFromRequest(w, r)
	if !ok {
		return
	}

	if r.ContentLength > h.maxBytes {
		writeError(w, http.StatusRequestEntityTooLarge, "payload_too_large")
		return
	}

	limited := http.MaxBytesReader(w, r.Body, h.maxBytes)
	reader := &countingReader{source: limited}

	prefix := make([]byte, len(zipLocalFileHeader))
	if _, err := io.ReadFull(reader, prefix); err != nil {
		if isPayloadTooLarge(reader.readErr) {
			writeError(w, http.StatusRequestEntityTooLarge, "payload_too_large")
			return
		}

		writeError(w, http.StatusBadRequest, "invalid_zip_signature")
		return
	}

	if !hasZipSignature(prefix) {
		writeError(w, http.StatusBadRequest, "invalid_zip_signature")
		return
	}

	contentType := strings.TrimSpace(r.Header.Get("Content-Type"))
	if contentType == "" {
		contentType = zipContentType
	}

	stream := io.MultiReader(bytes.NewReader(prefix), reader)

	size := r.ContentLength
	if size < 0 {
		size = -1
	}

	if err := h.store.Put(r.Context(), key, stream, size, contentType); err != nil {
		if isPayloadTooLarge(reader.readErr) || isPayloadTooLarge(err) {
			writeError(w, http.StatusRequestEntityTooLarge, "payload_too_large")
			return
		}

		h.logger.Error("media upload failed", "key", key, "error", err)
		writeError(w, http.StatusBadGateway, "storage_error")
		return
	}

	writeJSON(w, http.StatusOK, map[string]any{
		"key":  key,
		"size": reader.total,
	})
}

// Get handles GET /media/{key...} by streaming the object to the client while
// preserving Content-Type, Content-Length and ETag.
func (h *Handler) Get(w http.ResponseWriter, r *http.Request) {
	key, ok := keyFromRequest(w, r)
	if !ok {
		return
	}

	response, err := h.store.Get(r.Context(), key)
	if err != nil {
		h.logger.Error("media download failed", "key", key, "error", err)
		writeError(w, http.StatusBadGateway, "storage_error")
		return
	}
	defer response.Body.Close()

	switch {
	case response.StatusCode == http.StatusNotFound:
		writeError(w, http.StatusNotFound, "not_found")
		return
	case response.StatusCode < 200 || response.StatusCode >= 300:
		h.logger.Error("media download failed", "key", key, "status", response.StatusCode)
		writeError(w, http.StatusBadGateway, "storage_error")
		return
	}

	for _, name := range []string{"Content-Type", "Content-Length", "ETag", "Last-Modified"} {
		if value := response.Header.Get(name); value != "" {
			w.Header().Set(name, value)
		}
	}

	w.WriteHeader(response.StatusCode)

	if _, err := io.Copy(w, response.Body); err != nil {
		// Headers are already sent; only logging is possible here.
		h.logger.Warn("media download interrupted", "key", key, "error", err)
	}
}

// Delete handles DELETE /media/{key...}.
func (h *Handler) Delete(w http.ResponseWriter, r *http.Request) {
	key, ok := keyFromRequest(w, r)
	if !ok {
		return
	}

	status, err := h.store.Delete(r.Context(), key)
	if err != nil {
		h.logger.Error("media delete failed", "key", key, "error", err)
		writeError(w, http.StatusBadGateway, "storage_error")
		return
	}

	switch {
	case status == http.StatusNotFound:
		writeError(w, http.StatusNotFound, "not_found")
	case status >= 200 && status < 300:
		w.WriteHeader(http.StatusNoContent)
	default:
		h.logger.Error("media delete failed", "key", key, "status", status)
		writeError(w, http.StatusBadGateway, "storage_error")
	}
}

// Healthz is used by container orchestration probes.
func (h *Handler) Healthz(w http.ResponseWriter, _ *http.Request) {
	writeJSON(w, http.StatusOK, map[string]string{"status": "ok"})
}

func keyFromRequest(w http.ResponseWriter, r *http.Request) (string, bool) {
	key := strings.TrimPrefix(r.PathValue("key"), "/")

	if key == "" || len(key) > maxKeyLength || strings.ContainsRune(key, 0) {
		writeError(w, http.StatusBadRequest, "invalid_key")
		return "", false
	}

	for _, segment := range strings.Split(key, "/") {
		if segment == "" || segment == "." || segment == ".." {
			writeError(w, http.StatusBadRequest, "invalid_key")
			return "", false
		}
	}

	return key, true
}

func hasZipSignature(prefix []byte) bool {
	return bytes.Equal(prefix, zipLocalFileHeader) || bytes.Equal(prefix, zipEndOfCentralDirectory)
}

func isPayloadTooLarge(err error) bool {
	if err == nil {
		return false
	}

	var maxBytesError *http.MaxBytesError

	return errors.As(err, &maxBytesError)
}

func writeJSON(w http.ResponseWriter, status int, payload any) {
	body, err := json.Marshal(payload)
	if err != nil {
		http.Error(w, `{"error":"encoding_error"}`, http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.Header().Set("Content-Length", strconv.Itoa(len(body)))
	w.WriteHeader(status)
	_, _ = w.Write(body)
}

func writeError(w http.ResponseWriter, status int, code string) {
	writeJSON(w, status, map[string]string{"error": code})
}

// countingReader tracks how many bytes were read from the incoming body and
// remembers the first non-EOF error (MaxBytesReader reports 413 through it).
type countingReader struct {
	source   io.Reader
	total    int64
	readErr  error
	hasError bool
}

func (r *countingReader) Read(payload []byte) (int, error) {
	read, err := r.source.Read(payload)
	r.total += int64(read)

	if err != nil && !errors.Is(err, io.EOF) && !r.hasError {
		r.readErr = err
		r.hasError = true
	}

	return read, err
}
