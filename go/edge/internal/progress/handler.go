package progress

import (
	"context"
	"encoding/json"
	"errors"
	"io"
	"log/slog"
	"net/http"
	"strconv"
	"strings"
	"time"
)

const (
	// KeyPrefix mirrors RunnerProgressKvStorage::KEY_PREFIX.
	KeyPrefix = "runner.progress."
	// ChannelPrefix mirrors RunnerProgressPublisher::CHANNEL_PREFIX.
	ChannelPrefix = "unit_progress#"

	defaultProgressTTL      = 2 * time.Hour
	defaultPublishMarkerTTL = time.Second
)

const (
	CodeUnknownToken           = "runner.unknown_progress_token"
	CodeStorageError           = "runner.storage_error"
	CodeProgressCorrupted      = "runner.progress_corrupted"
	CodeRequestTooLarge        = "runner.steps_too_large"
	CodeInvalidSignature       = "runner.invalid_signature"
	CodeSignatureNotConfigured = "runner.signature_not_configured"
	defaultErrorStatusCode     = http.StatusInternalServerError
)

// KV is the minimal Redis contract needed by the progress handler.
type KV interface {
	Get(ctx context.Context, key string) (value string, found bool, err error)
	Set(ctx context.Context, key, value string, ttl time.Duration) error
	Exists(ctx context.Context, key string) (bool, error)
}

// Publisher is the minimal Centrifugo contract needed by the handler.
type Publisher interface {
	Publish(ctx context.Context, channel string, data []byte) error
}

// Options configures the progress gateway.
type Options struct {
	Limits           Limits
	ProgressTTL      time.Duration
	PublishMarkerTTL time.Duration
	Now              func() time.Time
	Logger           *slog.Logger
	// RunnerSecretMasterKey is the explicit RUNNER_SECRET_MASTER_KEY resolved
	// by config.Load. Empty means fail closed.
	RunnerSecretMasterKey string
}

// Handler implements POST /api/runner/saveSteps, GET /api/runner/progress/{id}
// and GET /healthz.
type Handler struct {
	kv                    KV
	publisher             Publisher
	limits                Limits
	progressTTL           time.Duration
	publishMarkerTTL      time.Duration
	now                   func() time.Time
	logger                *slog.Logger
	runnerSecretMasterKey string
}

// NewHandler wires the gateway with safe defaults.
func NewHandler(kv KV, publisher Publisher, options Options) *Handler {
	limits := options.Limits
	if limits.MaxContentBytes <= 0 {
		limits.MaxContentBytes = DefaultMaxStepsContentBytes
	}
	if limits.MaxSteps <= 0 {
		limits.MaxSteps = DefaultMaxSteps
	}
	if limits.MaxResponseBytes <= 0 {
		limits.MaxResponseBytes = DefaultMaxStepResponseBytes
	}

	progressTTL := options.ProgressTTL
	if progressTTL <= 0 {
		progressTTL = defaultProgressTTL
	}

	markerTTL := options.PublishMarkerTTL
	if markerTTL <= 0 {
		markerTTL = defaultPublishMarkerTTL
	}

	now := options.Now
	if now == nil {
		now = time.Now
	}

	logger := options.Logger
	if logger == nil {
		logger = slog.Default()
	}

	return &Handler{
		kv:                    kv,
		publisher:             publisher,
		limits:                limits,
		progressTTL:           progressTTL,
		publishMarkerTTL:      markerTTL,
		now:                   now,
		logger:                logger,
		runnerSecretMasterKey: strings.TrimSpace(options.RunnerSecretMasterKey),
	}
}

type saveStepsRequest struct {
	ResponseID   string `json:"responseId"`
	StepsContent string `json:"stepsContent"`
	Finished     bool   `json:"finished"`
	Success      bool   `json:"success"`
}

// SaveSteps handles POST /api/runner/saveSteps.
//
// Per-runner callback authentication (X-Runner-Id + X-Runner-Signature) is
// enforced here with the same v2 contract as the PHP path:
// hex(HMAC-SHA256(responseId . stepsContent, hex(HMAC-SHA256(runnerId,
// masterKey)))). A missing master key fails closed with 503; missing or
// invalid headers return 401 before any Redis write. Only then the progress
// token registered in Redis is checked, followed by size/decode limits, KV
// writes and throttled Centrifugo publishing.
func (h *Handler) SaveSteps(w http.ResponseWriter, r *http.Request) {
	ctx := r.Context()

	body := http.MaxBytesReader(w, r.Body, h.maxBodyBytes())
	decoder := json.NewDecoder(body)

	var request saveStepsRequest
	if err := decoder.Decode(&request); err != nil {
		var maxBytesError *http.MaxBytesError
		if errors.As(err, &maxBytesError) {
			h.writeError(w, http.StatusBadRequest, CodeRequestTooLarge)
			return
		}

		h.writeError(w, http.StatusBadRequest, CodeStepsMalformed)
		return
	}

	if _, err := decoder.Token(); !errors.Is(err, io.EOF) {
		h.writeError(w, http.StatusBadRequest, CodeStepsMalformed)
		return
	}

	// Signature is verified before any Redis write (and before the token
	// lookup, so unauthenticated requests never touch storage).
	if status, code := h.verifySaveStepsSignature(r, request.ResponseID, request.StepsContent); status != 0 {
		h.writeError(w, status, code)
		return
	}

	registered, err := h.kv.Exists(ctx, tokenKey(request.ResponseID))
	if err != nil {
		h.logger.Error("progress token lookup failed", "error", err)
		h.writeError(w, http.StatusInternalServerError, CodeStorageError)
		return
	}

	if !registered {
		h.writeError(w, http.StatusBadRequest, CodeUnknownToken)
		return
	}

	steps, stepError := DecodeSteps(request.StepsContent, h.limits)
	if stepError != nil {
		h.writeError(w, http.StatusBadRequest, stepError.Code)
		return
	}

	stepsJSON, err := json.Marshal(steps)
	if err != nil {
		h.logger.Error("normalized steps encoding failed", "error", err)
		h.writeError(w, http.StatusInternalServerError, CodeStorageError)
		return
	}

	now := h.now()
	unixtime := now.Unix()

	// Raw snapshot keeps the original bytes so the activity result path can
	// read back exactly what the runner sent.
	if err := h.kv.Set(ctx, request.ResponseID, SerializePHPString(request.StepsContent), h.progressTTL); err != nil {
		h.logger.Error("raw steps write failed", "error", err)
		h.writeError(w, http.StatusInternalServerError, CodeStorageError)
		return
	}

	progressPayload := buildProgressPayload(request.ResponseID, stepsJSON, request.Finished, request.Success, unixtime)
	if err := h.kv.Set(ctx, progressKey(request.ResponseID), SerializePHPString(string(progressPayload)), h.progressTTL); err != nil {
		h.logger.Error("progress write failed", "error", err)
		h.writeError(w, http.StatusInternalServerError, CodeStorageError)
		return
	}

	clientPayload := buildClientPayload(request.ResponseID, stepsJSON, request.Finished, request.Success, unixtime)
	h.publish(ctx, request.ResponseID, request.Finished, now, clientPayload)

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	_, _ = w.Write([]byte("true"))
}

type storedProgress struct {
	Token    string            `json:"token"`
	Steps    []json.RawMessage `json:"steps"`
	Finished bool              `json:"finished"`
	Success  bool              `json:"success"`
	Unixtime int64             `json:"unixtime"`
}

// GetProgress handles GET /api/runner/progress/{responseId}.
func (h *Handler) GetProgress(w http.ResponseWriter, r *http.Request) {
	responseID := r.PathValue("responseId")

	raw, found, err := h.kv.Get(r.Context(), progressKey(responseID))
	if err != nil {
		h.logger.Error("progress read failed", "error", err)
		h.writeError(w, http.StatusInternalServerError, CodeStorageError)
		return
	}

	if !found || raw == "" {
		h.writeRawJSON(w, http.StatusOK, []byte(`{"steps":[],"finished":false,"success":false,"updatedAt":0}`))
		return
	}

	payload := UnwrapPHPString(raw)

	var progress storedProgress
	if err := json.Unmarshal([]byte(payload), &progress); err != nil {
		h.logger.Error("stored progress is corrupted", "error", err)
		h.writeError(w, http.StatusInternalServerError, CodeProgressCorrupted)
		return
	}

	stepsJSON := []byte("[]")
	if progress.Steps != nil {
		stepsJSON, err = json.Marshal(progress.Steps)
		if err != nil {
			h.logger.Error("stored steps encoding failed", "error", err)
			h.writeError(w, http.StatusInternalServerError, CodeProgressCorrupted)
			return
		}
	}

	clientPayload := buildClientPayload(responseID, stepsJSON, progress.Finished, progress.Success, progress.Unixtime)
	h.writeRawJSON(w, http.StatusOK, clientPayload)
}

// Healthz handles GET /healthz.
func (h *Handler) Healthz(w http.ResponseWriter, r *http.Request) {
	h.writeRawJSON(w, http.StatusOK, []byte(`{"status":"ok"}`))
}

func (h *Handler) publish(ctx context.Context, responseID string, finished bool, now time.Time, payload []byte) {
	markerKey := publishMarkerKey(responseID)

	if !finished {
		exists, err := h.kv.Exists(ctx, markerKey)
		if err != nil {
			// Redis hiccups must not drop the final snapshot; degrade to a
			// best-effort publish instead of failing the callback.
			h.logger.Warn("publish marker lookup failed", "error", err)
		} else if exists {
			return
		}
	}

	markerValue := SerializePHPString(strconv.FormatInt(now.UnixMilli(), 10))
	if err := h.kv.Set(ctx, markerKey, markerValue, h.publishMarkerTTL); err != nil {
		h.logger.Warn("publish marker write failed", "error", err)
	}

	if err := h.publisher.Publish(ctx, ChannelPrefix+responseID, payload); err != nil {
		// Centrifugo outages are swallowed: pull still serves the fresh KV
		// snapshot, exactly like RunnerProgressPublisher.
		h.logger.Warn("centrifugo publish failed", "error", err)
	}
}

func (h *Handler) maxBodyBytes() int64 {
	if h.limits.MaxContentBytes <= 0 {
		return DefaultMaxStepsContentBytes*8 + (1 << 20)
	}

	return int64(h.limits.MaxContentBytes)*8 + (1 << 20)
}

func tokenKey(responseID string) string {
	return KeyPrefix + "token." + responseID
}

func progressKey(responseID string) string {
	return KeyPrefix + responseID
}

func publishMarkerKey(responseID string) string {
	return KeyPrefix + "lastpub." + responseID
}

func buildProgressPayload(token string, stepsJSON []byte, finished, success bool, unixtime int64) []byte {
	var b []byte
	b = append(b, `{"token":`...)
	b = append(b, encodeJSONString(token)...)
	b = append(b, `,"steps":`...)
	b = append(b, stepsJSON...)
	b = append(b, `,"finished":`...)
	b = append(b, boolJSON(finished)...)
	b = append(b, `,"success":`...)
	b = append(b, boolJSON(success)...)
	b = append(b, `,"unixtime":`...)
	b = strconv.AppendInt(b, unixtime, 10)
	b = append(b, '}')

	return b
}

func buildClientPayload(token string, stepsJSON []byte, finished, success bool, updatedAt int64) []byte {
	var b []byte
	b = append(b, `{"jobId":`...)
	b = append(b, encodeJSONString(token)...)
	b = append(b, `,"steps":`...)
	b = append(b, stepsJSON...)
	b = append(b, `,"finished":`...)
	b = append(b, boolJSON(finished)...)
	b = append(b, `,"success":`...)
	b = append(b, boolJSON(success)...)
	b = append(b, `,"updatedAt":`...)
	b = strconv.AppendInt(b, updatedAt, 10)
	b = append(b, '}')

	return b
}

func encodeJSONString(value string) []byte {
	encoded, err := json.Marshal(value)
	if err != nil {
		// Marshal of a string never fails; keep the contract total.
		return []byte(`""`)
	}

	return encoded
}

func boolJSON(value bool) []byte {
	if value {
		return []byte("true")
	}

	return []byte("false")
}

type errorEnvelope struct {
	Status  string `json:"status"`
	Message string `json:"message"`
	Code    string `json:"code"`
}

func (h *Handler) writeError(w http.ResponseWriter, status int, message string) {
	if status <= 0 {
		status = defaultErrorStatusCode
	}

	payload, err := json.Marshal(errorEnvelope{
		Status:  "error",
		Message: message,
		Code:    "DEFAULT",
	})
	if err != nil {
		payload = []byte(`{"status":"error","message":"runner.internal_error","code":"DEFAULT"}`)
	}

	h.writeRawJSON(w, status, payload)
}

func (h *Handler) writeRawJSON(w http.ResponseWriter, status int, payload []byte) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	_, _ = w.Write(payload)
}
