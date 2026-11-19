package centrifugo

import (
	"context"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"testing"
)

func TestPublishRequestShape(t *testing.T) {
	t.Parallel()

	var (
		gotMethod string
		gotAPIKey string
		gotBody   map[string]any
	)

	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		gotMethod = r.Method
		gotAPIKey = r.Header.Get("X-API-Key")

		if err := json.NewDecoder(r.Body).Decode(&gotBody); err != nil {
			t.Errorf("decode request: %v", err)
		}

		w.WriteHeader(http.StatusOK)
		_, _ = w.Write([]byte(`{"result":{}}`))
	}))
	t.Cleanup(server.Close)

	client := New(Options{Endpoint: server.URL, APIKey: "secret-key"})

	if err := client.Publish(context.Background(), "unit_progress#job-1", []byte(`{"jobId":"job-1"}`)); err != nil {
		t.Fatalf("Publish error: %v", err)
	}

	if gotMethod != http.MethodPost {
		t.Fatalf("method = %q, want POST", gotMethod)
	}

	if gotAPIKey != "secret-key" {
		t.Fatalf("X-API-Key = %q", gotAPIKey)
	}

	if gotBody["method"] != "publish" {
		t.Fatalf("method field = %v", gotBody["method"])
	}

	params, ok := gotBody["params"].(map[string]any)
	if !ok {
		t.Fatalf("params = %#v", gotBody["params"])
	}

	if params["channel"] != "unit_progress#job-1" {
		t.Fatalf("channel = %v", params["channel"])
	}

	data, ok := params["data"].(map[string]any)
	if !ok {
		t.Fatalf("data = %#v", params["data"])
	}

	if data["jobId"] != "job-1" {
		t.Fatalf("data.jobId = %v", data["jobId"])
	}
}

func TestPublishReturnsErrorOnNon2xx(t *testing.T) {
	t.Parallel()

	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusInternalServerError)
	}))
	t.Cleanup(server.Close)

	client := New(Options{Endpoint: server.URL, APIKey: "secret-key"})

	if err := client.Publish(context.Background(), "unit_progress#job-1", []byte(`{}`)); err == nil {
		t.Fatalf("Publish error = nil, want non-2xx error")
	}
}

func TestPublishWithoutEndpoint(t *testing.T) {
	t.Parallel()

	client := New(Options{})

	if err := client.Publish(context.Background(), "unit_progress#job-1", []byte(`{}`)); err == nil {
		t.Fatalf("Publish error = nil, want configuration error")
	}
}
