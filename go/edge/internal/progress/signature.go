package progress

import (
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"net/http"
	"strings"
)

const (
	// RunnerIDHeader carries the registered runner identifier (contract v2).
	RunnerIDHeader = "X-Runner-Id"
	// RunnerSignatureHeader carries the per-runner callback signature.
	RunnerSignatureHeader = "X-Runner-Signature"
)

// runnerSecret mirrors PHP RunnerSecretGenerator::generate():
// hex(HMAC-SHA256(runnerId, masterKey)).
//
// The returned bytes are the lowercase hex representation (the PHP HMAC
// result is a string), not the raw digest: the second HMAC must use exactly
// the same key bytes as the backend.
func runnerSecret(masterKey, runnerID string) []byte {
	mac := hmac.New(sha256.New, []byte(masterKey))
	mac.Write([]byte(runnerID))

	encoded := make([]byte, hex.EncodedLen(mac.Size()))
	hex.Encode(encoded, mac.Sum(nil))

	return encoded
}

// expectedSaveStepsSignature mirrors PHP RunnerCallbackSignatureChecker for
// saveSteps: hex(HMAC-SHA256(responseId . stepsContent, runnerSecret)).
func expectedSaveStepsSignature(masterKey, runnerID, responseID, stepsContent string) []byte {
	mac := hmac.New(sha256.New, runnerSecret(masterKey, runnerID))
	mac.Write([]byte(responseID))
	mac.Write([]byte(stepsContent))

	return mac.Sum(nil)
}

// verifySaveStepsSignature enforces the v2 callback contract. It returns a
// non-zero HTTP status and public error code when the request must be rejected.
//
// Order: fail closed when no master key is configured (503), then require both
// headers and a constant-time matching signature (401). Secrets and signatures
// are never logged.
func (h *Handler) verifySaveStepsSignature(r *http.Request, responseID, stepsContent string) (int, string) {
	if strings.TrimSpace(h.runnerSecretMasterKey) == "" {
		h.logger.Error("runner secret master key is not configured; rejecting saveSteps")
		return http.StatusServiceUnavailable, CodeSignatureNotConfigured
	}

	runnerID := strings.TrimSpace(r.Header.Get(RunnerIDHeader))
	provided := strings.TrimSpace(r.Header.Get(RunnerSignatureHeader))

	if runnerID == "" || provided == "" {
		return http.StatusUnauthorized, CodeInvalidSignature
	}

	decoded, err := hex.DecodeString(provided)
	if err != nil {
		return http.StatusUnauthorized, CodeInvalidSignature
	}

	expected := expectedSaveStepsSignature(h.runnerSecretMasterKey, runnerID, responseID, stepsContent)
	if !hmac.Equal(decoded, expected) {
		return http.StatusUnauthorized, CodeInvalidSignature
	}

	return 0, ""
}
