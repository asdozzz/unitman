package s3

import (
	"context"
	"io"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
	"time"
)

func fixedClock() func() time.Time {
	return func() time.Time {
		return time.Date(2024, 6, 1, 12, 0, 0, 0, time.UTC)
	}
}

func newTestClient(t *testing.T, endpoint string, pathStyle bool) *Client {
	t.Helper()

	client, err := New(Options{
		Endpoint:     endpoint,
		Bucket:       "unitman-mediateka",
		Region:       "us-east-1",
		AccessKey:    "access",
		SecretKey:    "secret",
		UsePathStyle: pathStyle,
		Now:          fixedClock(),
	})
	if err != nil {
		t.Fatalf("build client: %v", err)
	}

	return client
}

func TestTargetPathStyle(t *testing.T) {
	t.Parallel()

	client := newTestClient(t, "http://minio:9000", true)

	host, path := client.target("units/abc/файл 1.zip")
	if host != "minio:9000" {
		t.Fatalf("host = %q", host)
	}

	want := "/unitman-mediateka/units/abc/%D1%84%D0%B0%D0%B9%D0%BB%201.zip"
	if path != want {
		t.Fatalf("path = %q, want %q", path, want)
	}
}

func TestTargetVirtualHostStyle(t *testing.T) {
	t.Parallel()

	client := newTestClient(t, "http://minio:9000", false)

	host, path := client.target("units/abc/a.zip")
	if host != "unitman-mediateka.minio:9000" {
		t.Fatalf("host = %q", host)
	}

	if path != "/units/abc/a.zip" {
		t.Fatalf("path = %q", path)
	}
}

func TestPutStreamsBodyWithSignedHeaders(t *testing.T) {
	t.Parallel()

	type captured struct {
		method      string
		path        string
		host        string
		payloadHash string
		authorized  bool
		contentType string
		body        string
	}

	requests := make(chan captured, 1)

	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		body, _ := io.ReadAll(r.Body)

		requests <- captured{
			method:      r.Method,
			path:        r.URL.EscapedPath(),
			host:        r.Host,
			payloadHash: r.Header.Get("x-amz-content-sha256"),
			authorized:  strings.HasPrefix(r.Header.Get("Authorization"), "AWS4-HMAC-SHA256 "),
			contentType: r.Header.Get("Content-Type"),
			body:        string(body),
		}

		w.WriteHeader(http.StatusOK)
	}))
	defer server.Close()

	client := newTestClient(t, server.URL, true)

	const payload = "PK\x03\x04streamed-payload"
	err := client.Put(context.Background(), "drafts/a.zip", strings.NewReader(payload), int64(len(payload)), "application/zip")
	if err != nil {
		t.Fatalf("put: %v", err)
	}

	got := <-requests

	if got.method != http.MethodPut {
		t.Fatalf("method = %q", got.method)
	}

	if got.path != "/unitman-mediateka/drafts/a.zip" {
		t.Fatalf("path = %q", got.path)
	}

	if got.host != strings.TrimPrefix(server.URL, "http://") {
		t.Fatalf("host = %q", got.host)
	}

	if got.payloadHash != UnsignedPayload {
		t.Fatalf("payload hash = %q, want %q", got.payloadHash, UnsignedPayload)
	}

	if !got.authorized {
		t.Fatalf("missing AWS4-HMAC-SHA256 authorization header")
	}

	if got.contentType != "application/zip" {
		t.Fatalf("content type = %q", got.contentType)
	}

	if got.body != payload {
		t.Fatalf("body = %q, want %q", got.body, payload)
	}
}

func TestPutReturnsStatusError(t *testing.T) {
	t.Parallel()

	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) {
		http.Error(w, "access denied", http.StatusForbidden)
	}))
	defer server.Close()

	client := newTestClient(t, server.URL, true)

	err := client.Put(context.Background(), "drafts/a.zip", strings.NewReader("PK\x03\x04"), 4, "")
	if err == nil {
		t.Fatal("expected an error")
	}

	statusErr, ok := err.(*StatusError)
	if !ok {
		t.Fatalf("error type = %T, want *StatusError", err)
	}

	if statusErr.StatusCode != http.StatusForbidden {
		t.Fatalf("status = %d", statusErr.StatusCode)
	}
}

func TestGetReturnsNonSuccessResponse(t *testing.T) {
	t.Parallel()

	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Header.Get("x-amz-content-sha256") != EmptyPayloadSHA256 {
			t.Errorf("get payload hash = %q", r.Header.Get("x-amz-content-sha256"))
		}

		http.NotFound(w, r)
	}))
	defer server.Close()

	client := newTestClient(t, server.URL, true)

	response, err := client.Get(context.Background(), "missing.zip")
	if err != nil {
		t.Fatalf("get: %v", err)
	}
	defer response.Body.Close()

	if response.StatusCode != http.StatusNotFound {
		t.Fatalf("status = %d", response.StatusCode)
	}
}

func TestDeleteReturnsStatusCode(t *testing.T) {
	t.Parallel()

	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) {
		w.WriteHeader(http.StatusNoContent)
	}))
	defer server.Close()

	client := newTestClient(t, server.URL, true)

	status, err := client.Delete(context.Background(), "drafts/a.zip")
	if err != nil {
		t.Fatalf("delete: %v", err)
	}

	if status != http.StatusNoContent {
		t.Fatalf("status = %d", status)
	}
}
