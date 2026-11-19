package progress

import (
	"context"
	"encoding/json"
	"io"
	"log/slog"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
	"time"
)

const (
	testToken         = "job-1"
	testFixedUnixTime = int64(1730000000)
)

var testFixedNow = time.UnixMilli(1730000000123)

type fakeValue struct {
	value string
	ttl   time.Duration
}

type fakeKV struct {
	data      map[string]fakeValue
	setCalls  []string
	getErr    error
	setErr    error
	existsErr error
}

func newFakeKV() *fakeKV {
	return &fakeKV{data: make(map[string]fakeValue)}
}

func (f *fakeKV) Get(_ context.Context, key string) (string, bool, error) {
	if f.getErr != nil {
		return "", false, f.getErr
	}

	value, found := f.data[key]

	return value.value, found, nil
}

func (f *fakeKV) Set(_ context.Context, key, value string, ttl time.Duration) error {
	if f.setErr != nil {
		return f.setErr
	}

	f.data[key] = fakeValue{value: value, ttl: ttl}
	f.setCalls = append(f.setCalls, key)

	return nil
}

func (f *fakeKV) Exists(_ context.Context, key string) (bool, error) {
	if f.existsErr != nil {
		return false, f.existsErr
	}

	_, found := f.data[key]

	return found, nil
}

type publishCall struct {
	channel string
	data    []byte
}

type fakePublisher struct {
	calls []publishCall
	err   error
}

func (p *fakePublisher) Publish(_ context.Context, channel string, data []byte) error {
	p.calls = append(p.calls, publishCall{channel: channel, data: append([]byte(nil), data...)})

	return p.err
}

func newTestHandler(kv KV, publisher Publisher, secret string) *Handler {
	return NewHandler(kv, publisher, Options{
		Secret: secret,
		Now:    func() time.Time { return testFixedNow },
		Logger: slog.New(slog.NewTextHandler(io.Discard, nil)),
	})
}

func registerProgressToken(kv *fakeKV, token string) {
	// Exactly what tests/load/register-progress-token.sh writes.
	kv.data[tokenKey(token)] = fakeValue{
		value: SerializePHPString("1795069425"),
		ttl:   2 * time.Hour,
	}
}

type saveStepsBody struct {
	ResponseID   string `json:"responseId"`
	StepsContent string `json:"stepsContent"`
	Finished     bool   `json:"finished"`
	Success      bool   `json:"success"`
}

func doSaveSteps(t *testing.T, handler *Handler, body saveStepsBody, signature string) *httptest.ResponseRecorder {
	t.Helper()

	encoded, err := json.Marshal(body)
	if err != nil {
		t.Fatalf("marshal request: %v", err)
	}

	request := httptest.NewRequest(http.MethodPost, "/api/runner/saveSteps", strings.NewReader(string(encoded)))
	request.Header.Set("Content-Type", "application/json")
	if signature != "" {
		request.Header.Set(SignatureHeader, signature)
	}

	recorder := httptest.NewRecorder()
	handler.SaveSteps(recorder, request)

	return recorder
}

func decodeErrorEnvelope(t *testing.T, recorder *httptest.ResponseRecorder) errorEnvelope {
	t.Helper()

	var envelope errorEnvelope
	if err := json.Unmarshal(recorder.Body.Bytes(), &envelope); err != nil {
		t.Fatalf("decode error envelope %q: %v", recorder.Body.String(), err)
	}

	return envelope
}

func TestSaveStepsHappyPathWritesPHPCompatibleKVAndPublishes(t *testing.T) {
	t.Parallel()

	kv := newFakeKV()
	registerProgressToken(kv, testToken)

	publisher := &fakePublisher{}
	handler := newTestHandler(kv, publisher, "")

	stepsContent := `[{"Command":"docker build","Response":"ok","Success":true,"Unixtime":1730000000,"unixtimeStart":"1729999975"}]`

	recorder := doSaveSteps(t, handler, saveStepsBody{
		ResponseID:   testToken,
		StepsContent: stepsContent,
		Finished:     false,
		Success:      true,
	}, "")

	if recorder.Code != http.StatusOK {
		t.Fatalf("status = %d, want 200 (%s)", recorder.Code, recorder.Body.String())
	}

	if recorder.Body.String() != "true" {
		t.Fatalf("body = %q, want true", recorder.Body.String())
	}

	rawSteps, ok := kv.data[testToken]
	if !ok {
		t.Fatalf("raw steps key %q missing", testToken)
	}

	if rawSteps.value != SerializePHPString(stepsContent) {
		t.Fatalf("raw steps = %q, want PHP-serialized original content", rawSteps.value)
	}

	if rawSteps.ttl != 2*time.Hour {
		t.Fatalf("raw steps ttl = %s, want 2h", rawSteps.ttl)
	}

	rawProgress, ok := kv.data[progressKey(testToken)]
	if !ok {
		t.Fatalf("progress key missing")
	}

	progressJSON := UnwrapPHPString(rawProgress.value)

	var stored struct {
		Token    string            `json:"token"`
		Steps    []json.RawMessage `json:"steps"`
		Finished bool              `json:"finished"`
		Success  bool              `json:"success"`
		Unixtime int64             `json:"unixtime"`
	}
	if err := json.Unmarshal([]byte(progressJSON), &stored); err != nil {
		t.Fatalf("progress is not JSON: %v (%s)", err, progressJSON)
	}

	if stored.Token != testToken || stored.Finished || !stored.Success || stored.Unixtime != testFixedUnixTime {
		t.Fatalf("unexpected progress payload: %+v", stored)
	}

	const wantStep = `{"Command":"docker build","Response":"ok","Success":true,"Unixtime":1730000000,"UnixtimeStart":1729999975}`
	if got := string(stored.Steps[0]); got != wantStep {
		t.Fatalf("stored step = %s, want %s", got, wantStep)
	}

	if len(publisher.calls) != 1 {
		t.Fatalf("publish calls = %d, want 1", len(publisher.calls))
	}

	if publisher.calls[0].channel != ChannelPrefix+testToken {
		t.Fatalf("channel = %q", publisher.calls[0].channel)
	}

	const wantClientPayload = `{"jobId":"job-1","steps":[{"Command":"docker build","Response":"ok","Success":true,"Unixtime":1730000000,"UnixtimeStart":1729999975}],"finished":false,"success":true,"updatedAt":1730000000}`
	if got := string(publisher.calls[0].data); got != wantClientPayload {
		t.Fatalf("client payload = %s, want %s", got, wantClientPayload)
	}

	marker, ok := kv.data[publishMarkerKey(testToken)]
	if !ok {
		t.Fatalf("publish marker missing")
	}

	if marker.value != `s:13:"1730000000123";` {
		t.Fatalf("marker = %q, want PHP-serialized milliseconds", marker.value)
	}

	if marker.ttl != time.Second {
		t.Fatalf("marker ttl = %s, want 1s", marker.ttl)
	}
}

func TestSaveStepsUnknownToken(t *testing.T) {
	t.Parallel()

	kv := newFakeKV()
	publisher := &fakePublisher{}
	handler := newTestHandler(kv, publisher, "")

	recorder := doSaveSteps(t, handler, saveStepsBody{
		ResponseID:   "missing",
		StepsContent: `[]`,
	}, "")

	if recorder.Code != http.StatusBadRequest {
		t.Fatalf("status = %d, want 400", recorder.Code)
	}

	envelope := decodeErrorEnvelope(t, recorder)
	if envelope.Message != CodeUnknownToken || envelope.Status != "error" || envelope.Code != "DEFAULT" {
		t.Fatalf("envelope = %+v", envelope)
	}

	if len(kv.setCalls) != 0 {
		t.Fatalf("set calls = %d, want 0", len(kv.setCalls))
	}

	if len(publisher.calls) != 0 {
		t.Fatalf("publish calls = %d, want 0", len(publisher.calls))
	}
}

func TestSaveStepsSignature(t *testing.T) {
	t.Parallel()

	const secret = "runner-secret"
	stepsContent := `[{"Command":"x","Unixtime":10}]`
	body := saveStepsBody{ResponseID: testToken, StepsContent: stepsContent}

	t.Run("missing signature rejected", func(t *testing.T) {
		t.Parallel()

		kv := newFakeKV()
		registerProgressToken(kv, testToken)
		handler := newTestHandler(kv, &fakePublisher{}, secret)

		recorder := doSaveSteps(t, handler, body, "")

		if recorder.Code != http.StatusForbidden {
			t.Fatalf("status = %d, want 403", recorder.Code)
		}

		if envelope := decodeErrorEnvelope(t, recorder); envelope.Message != CodeInvalidSignature {
			t.Fatalf("envelope = %+v", envelope)
		}

		if len(kv.setCalls) != 0 {
			t.Fatalf("set calls = %d, want 0", len(kv.setCalls))
		}
	})

	t.Run("valid signature accepted", func(t *testing.T) {
		t.Parallel()

		kv := newFakeKV()
		registerProgressToken(kv, testToken)
		handler := newTestHandler(kv, &fakePublisher{}, secret)

		signature := ComputeSignature(secret, body.ResponseID, body.StepsContent)
		recorder := doSaveSteps(t, handler, body, signature)

		if recorder.Code != http.StatusOK || recorder.Body.String() != "true" {
			t.Fatalf("status = %d body = %q", recorder.Code, recorder.Body.String())
		}
	})

	t.Run("empty secret bypasses verification", func(t *testing.T) {
		t.Parallel()

		kv := newFakeKV()
		registerProgressToken(kv, testToken)
		handler := newTestHandler(kv, &fakePublisher{}, "")

		recorder := doSaveSteps(t, handler, body, "totally-wrong")

		if recorder.Code != http.StatusOK || recorder.Body.String() != "true" {
			t.Fatalf("status = %d body = %q", recorder.Code, recorder.Body.String())
		}
	})
}

func TestSaveStepsThrottlesPublishing(t *testing.T) {
	t.Parallel()

	kv := newFakeKV()
	registerProgressToken(kv, testToken)
	kv.data[publishMarkerKey(testToken)] = fakeValue{
		value: SerializePHPString("1730000000001"),
		ttl:   time.Second,
	}

	publisher := &fakePublisher{}
	handler := newTestHandler(kv, publisher, "")

	recorder := doSaveSteps(t, handler, saveStepsBody{
		ResponseID:   testToken,
		StepsContent: `[{"Command":"x","Unixtime":10}]`,
		Finished:     false,
	}, "")

	if recorder.Code != http.StatusOK {
		t.Fatalf("status = %d", recorder.Code)
	}

	if len(publisher.calls) != 0 {
		t.Fatalf("publish calls = %d, want 0 (throttled)", len(publisher.calls))
	}

	// KV still updated so the pull path is current.
	if _, ok := kv.data[progressKey(testToken)]; !ok {
		t.Fatalf("progress key not written while throttled")
	}
}

func TestSaveStepsFinishedBypassesThrottle(t *testing.T) {
	t.Parallel()

	kv := newFakeKV()
	registerProgressToken(kv, testToken)
	kv.data[publishMarkerKey(testToken)] = fakeValue{
		value: SerializePHPString("1730000000001"),
		ttl:   time.Second,
	}

	publisher := &fakePublisher{}
	handler := newTestHandler(kv, publisher, "")

	recorder := doSaveSteps(t, handler, saveStepsBody{
		ResponseID:   testToken,
		StepsContent: `[{"Command":"x","Unixtime":10}]`,
		Finished:     true,
		Success:      true,
	}, "")

	if recorder.Code != http.StatusOK {
		t.Fatalf("status = %d", recorder.Code)
	}

	if len(publisher.calls) != 1 {
		t.Fatalf("publish calls = %d, want 1 (finished bypass)", len(publisher.calls))
	}
}

func TestSaveStepsPublishErrorsAreSwallowed(t *testing.T) {
	t.Parallel()

	kv := newFakeKV()
	registerProgressToken(kv, testToken)

	publisher := &fakePublisher{err: context.DeadlineExceeded}
	handler := newTestHandler(kv, publisher, "")

	recorder := doSaveSteps(t, handler, saveStepsBody{
		ResponseID:   testToken,
		StepsContent: `[{"Command":"x","Unixtime":10}]`,
	}, "")

	if recorder.Code != http.StatusOK || recorder.Body.String() != "true" {
		t.Fatalf("status = %d body = %q", recorder.Code, recorder.Body.String())
	}
}

func TestSaveStepsLimitErrors(t *testing.T) {
	t.Parallel()

	cases := []struct {
		name    string
		limits  Limits
		content string
		want    string
	}{
		{
			name:    "content size",
			limits:  Limits{MaxContentBytes: 3, MaxSteps: 10, MaxResponseBytes: 10},
			content: `[{}]`,
			want:    CodeStepsTooLarge,
		},
		{
			name:    "step count",
			limits:  Limits{MaxContentBytes: 1024, MaxSteps: 1, MaxResponseBytes: 10},
			content: `[{},{}]`,
			want:    CodeStepsTooMany,
		},
		{
			name:    "response size",
			limits:  Limits{MaxContentBytes: 1024, MaxSteps: 10, MaxResponseBytes: 3},
			content: `[{"Response":"abcd"}]`,
			want:    CodeStepTooLarge,
		},
	}

	for _, tc := range cases {
		tc := tc

		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()

			kv := newFakeKV()
			registerProgressToken(kv, testToken)

			handler := NewHandler(kv, &fakePublisher{}, Options{
				Limits: tc.limits,
				Now:    func() time.Time { return testFixedNow },
				Logger: slog.New(slog.NewTextHandler(io.Discard, nil)),
			})

			recorder := doSaveSteps(t, handler, saveStepsBody{
				ResponseID:   testToken,
				StepsContent: tc.content,
			}, "")

			if recorder.Code != http.StatusBadRequest {
				t.Fatalf("status = %d, want 400", recorder.Code)
			}

			if envelope := decodeErrorEnvelope(t, recorder); envelope.Message != tc.want {
				t.Fatalf("message = %q, want %q", envelope.Message, tc.want)
			}

			if len(kv.setCalls) != 0 {
				t.Fatalf("set calls = %d, want 0", len(kv.setCalls))
			}
		})
	}
}

func TestSaveStepsRejectsMalformedJSON(t *testing.T) {
	t.Parallel()

	kv := newFakeKV()
	registerProgressToken(kv, testToken)
	handler := newTestHandler(kv, &fakePublisher{}, "")

	encoded, _ := json.Marshal(saveStepsBody{
		ResponseID:   testToken,
		StepsContent: `{not-an-array}`,
	})

	request := httptest.NewRequest(http.MethodPost, "/api/runner/saveSteps", strings.NewReader(string(encoded)))
	recorder := httptest.NewRecorder()
	handler.SaveSteps(recorder, request)

	if recorder.Code != http.StatusBadRequest {
		t.Fatalf("status = %d, want 400", recorder.Code)
	}

	if envelope := decodeErrorEnvelope(t, recorder); envelope.Message != CodeStepsMalformed {
		t.Fatalf("message = %q", envelope.Message)
	}
}

func TestGetProgressReadsPHPWrittenSnapshot(t *testing.T) {
	t.Parallel()

	kv := newFakeKV()
	// Shape written by PHP's RunnerProgressKvStorage through RoadRunner KV.
	stored := `{"token":"job-1","steps":[{"Command":"x","UnixtimeStart":5}],"finished":true,"success":false,"unixtime":123}`
	kv.data[progressKey(testToken)] = fakeValue{
		value: SerializePHPString(stored),
		ttl:   2 * time.Hour,
	}

	handler := newTestHandler(kv, &fakePublisher{}, "")

	request := httptest.NewRequest(http.MethodGet, "/api/runner/progress/"+testToken, nil)
	request.SetPathValue("responseId", testToken)
	recorder := httptest.NewRecorder()
	handler.GetProgress(recorder, request)

	if recorder.Code != http.StatusOK {
		t.Fatalf("status = %d", recorder.Code)
	}

	const want = `{"jobId":"job-1","steps":[{"Command":"x","UnixtimeStart":5}],"finished":true,"success":false,"updatedAt":123}`
	if got := recorder.Body.String(); got != want {
		t.Fatalf("body = %s, want %s", got, want)
	}
}

func TestGetProgressMissingKeyReturnsEmptySnapshot(t *testing.T) {
	t.Parallel()

	kv := newFakeKV()
	handler := newTestHandler(kv, &fakePublisher{}, "")

	request := httptest.NewRequest(http.MethodGet, "/api/runner/progress/"+testToken, nil)
	request.SetPathValue("responseId", testToken)
	recorder := httptest.NewRecorder()
	handler.GetProgress(recorder, request)

	if recorder.Code != http.StatusOK {
		t.Fatalf("status = %d", recorder.Code)
	}

	const want = `{"steps":[],"finished":false,"success":false,"updatedAt":0}`
	if got := recorder.Body.String(); got != want {
		t.Fatalf("body = %s, want %s", got, want)
	}
}

func TestHealthz(t *testing.T) {
	t.Parallel()

	handler := newTestHandler(newFakeKV(), &fakePublisher{}, "")
	recorder := httptest.NewRecorder()

	handler.Healthz(recorder, httptest.NewRequest(http.MethodGet, "/healthz", nil))

	if recorder.Code != http.StatusOK {
		t.Fatalf("status = %d", recorder.Code)
	}

	if got := recorder.Body.String(); got != `{"status":"ok"}` {
		t.Fatalf("body = %s", got)
	}
}
