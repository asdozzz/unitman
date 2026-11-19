package progress

import (
	"crypto/hmac"
	"crypto/sha256"
	"crypto/subtle"
	"encoding/hex"
)

// SignatureHeader is the callback signature header used by the Go runner.
const SignatureHeader = "X-Runner-Signature"

// ComputeSignature returns lowercase hex HMAC-SHA256(responseId+stepsContent).
func ComputeSignature(secret, responseID, stepsContent string) string {
	mac := hmac.New(sha256.New, []byte(secret))
	_, _ = mac.Write([]byte(responseID))
	_, _ = mac.Write([]byte(stepsContent))

	return hex.EncodeToString(mac.Sum(nil))
}

// VerifySignature mirrors PHP's RunnerCallbackSignatureChecker::estPodpisValidna:
// an empty secret disables verification, a missing signature fails, and the
// comparison is case-sensitive and constant time.
func VerifySignature(secret, responseID, stepsContent, signature string) bool {
	if secret == "" {
		return true
	}

	if signature == "" {
		return false
	}

	expected := ComputeSignature(secret, responseID, stepsContent)

	return subtle.ConstantTimeCompare([]byte(expected), []byte(signature)) == 1
}
