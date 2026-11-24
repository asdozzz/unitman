package progress

import (
	"encoding/hex"
	"io"
	"log/slog"
	"net/http"
	"testing"
	"time"
)

// Test vector generated with the PHP backend:
//
//	$runnerSecret = hash_hmac('sha256', 'runner-test', 'master-key-secret');
//	$signature = hash_hmac('sha256', 'job-1' . $stepsContent, $runnerSecret);
//
// Keeping it fixed proves the Go gateway and RunnerCallbackSignatureChecker
// compute the same bytes.
const (
	phpVectorMasterKey  = "master-key-secret"
	phpVectorRunnerID   = "runner-test"
	phpVectorResponseID = "job-1"
	phpVectorSteps      = `[{"Command":"echo ok","Unixtime":1730000000}]`
	phpVectorSignature  = "b79bbfc63661189f92fb50c126b89f9edc04092280d00e276e6399b1ccb2477c"
)

func TestSignatureMatchesPHPTestVector(t *testing.T) {
	t.Parallel()

	if got := signSaveSteps(phpVectorRunnerID, phpVectorMasterKey, phpVectorResponseID, phpVectorSteps); got != phpVectorSignature {
		t.Fatalf("signature = %s, want PHP vector %s", got, phpVectorSignature)
	}

	if got := hex.EncodeToString(expectedSaveStepsSignature(phpVectorMasterKey, phpVectorRunnerID, phpVectorResponseID, phpVectorSteps)); got != phpVectorSignature {
		t.Fatalf("expected signature = %s, want PHP vector %s", got, phpVectorSignature)
	}
}

func TestSaveStepsAcceptsPHPTestVector(t *testing.T) {
	t.Parallel()

	kv := newFakeKV()
	registerProgressToken(kv, phpVectorResponseID)

	publisher := &fakePublisher{}
	handler := NewHandler(kv, publisher, Options{
		Now:                   func() time.Time { return testFixedNow },
		Logger:                slog.New(slog.NewTextHandler(io.Discard, nil)),
		RunnerSecretMasterKey: phpVectorMasterKey,
	})

	recorder := doSaveStepsSigned(t, handler, saveStepsBody{
		ResponseID:   phpVectorResponseID,
		StepsContent: phpVectorSteps,
		Success:      true,
	}, phpVectorRunnerID, phpVectorSignature)

	if recorder.Code != http.StatusOK || recorder.Body.String() != "true" {
		t.Fatalf("status = %d body = %q, want 200 true", recorder.Code, recorder.Body.String())
	}

	if _, ok := kv.data[progressKey(phpVectorResponseID)]; !ok {
		t.Fatalf("progress was not written for a valid signature")
	}
}

func TestSaveStepsRejectsInvalidSignature(t *testing.T) {
	t.Parallel()

	kv := newFakeKV()
	registerProgressToken(kv, testToken)

	publisher := &fakePublisher{}
	handler := newTestHandler(kv, publisher)

	body := saveStepsBody{
		ResponseID:   testToken,
		StepsContent: `[{"Command":"x","Unixtime":10}]`,
	}

	recorder := doSaveStepsSigned(t, handler, body, testRunnerID, "deadbeef")

	if recorder.Code != http.StatusUnauthorized {
		t.Fatalf("status = %d, want 401", recorder.Code)
	}

	if envelope := decodeErrorEnvelope(t, recorder); envelope.Message != CodeInvalidSignature {
		t.Fatalf("message = %q, want %q", envelope.Message, CodeInvalidSignature)
	}

	assertRejectedWithoutWrites(t, kv, publisher)
}

func TestSaveStepsRejectsMissingRunnerID(t *testing.T) {
	t.Parallel()

	kv := newFakeKV()
	registerProgressToken(kv, testToken)

	publisher := &fakePublisher{}
	handler := newTestHandler(kv, publisher)

	body := saveStepsBody{
		ResponseID:   testToken,
		StepsContent: `[{"Command":"x","Unixtime":10}]`,
	}

	recorder := doSaveStepsSigned(
		t,
		handler,
		body,
		"",
		signSaveSteps(testRunnerID, testMasterKey, body.ResponseID, body.StepsContent),
	)

	if recorder.Code != http.StatusUnauthorized {
		t.Fatalf("status = %d, want 401", recorder.Code)
	}

	if envelope := decodeErrorEnvelope(t, recorder); envelope.Message != CodeInvalidSignature {
		t.Fatalf("message = %q, want %q", envelope.Message, CodeInvalidSignature)
	}

	assertRejectedWithoutWrites(t, kv, publisher)
}

func TestSaveStepsRejectsMissingSignature(t *testing.T) {
	t.Parallel()

	kv := newFakeKV()
	registerProgressToken(kv, testToken)

	publisher := &fakePublisher{}
	handler := newTestHandler(kv, publisher)

	recorder := doSaveStepsSigned(t, handler, saveStepsBody{
		ResponseID:   testToken,
		StepsContent: `[{"Command":"x","Unixtime":10}]`,
	}, testRunnerID, "")

	if recorder.Code != http.StatusUnauthorized {
		t.Fatalf("status = %d, want 401", recorder.Code)
	}

	if envelope := decodeErrorEnvelope(t, recorder); envelope.Message != CodeInvalidSignature {
		t.Fatalf("message = %q, want %q", envelope.Message, CodeInvalidSignature)
	}

	assertRejectedWithoutWrites(t, kv, publisher)
}

func TestSaveStepsFailsClosedWithoutMasterKey(t *testing.T) {
	t.Parallel()

	kv := newFakeKV()
	registerProgressToken(kv, testToken)

	publisher := &fakePublisher{}
	handler := NewHandler(kv, publisher, Options{
		Now:    func() time.Time { return testFixedNow },
		Logger: slog.New(slog.NewTextHandler(io.Discard, nil)),
		// RunnerSecretMasterKey intentionally empty.
	})

	body := saveStepsBody{
		ResponseID:   testToken,
		StepsContent: `[{"Command":"x","Unixtime":10}]`,
	}

	recorder := doSaveStepsSigned(
		t,
		handler,
		body,
		testRunnerID,
		signSaveSteps(testRunnerID, testMasterKey, body.ResponseID, body.StepsContent),
	)

	if recorder.Code != http.StatusServiceUnavailable {
		t.Fatalf("status = %d, want 503 (fail closed)", recorder.Code)
	}

	if envelope := decodeErrorEnvelope(t, recorder); envelope.Message != CodeSignatureNotConfigured {
		t.Fatalf("message = %q, want %q", envelope.Message, CodeSignatureNotConfigured)
	}

	assertRejectedWithoutWrites(t, kv, publisher)
}

func assertRejectedWithoutWrites(t *testing.T, kv *fakeKV, publisher *fakePublisher) {
	t.Helper()

	if len(kv.setCalls) != 0 {
		t.Fatalf("set calls = %d, want 0 (no Redis writes on rejection)", len(kv.setCalls))
	}

	if len(publisher.calls) != 0 {
		t.Fatalf("publish calls = %d, want 0", len(publisher.calls))
	}
}
