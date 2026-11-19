package media

import (
	"bytes"
	"encoding/json"
	"io"
	"net/http"
	"net/http/httptest"
	"strconv"
	"strings"
	"sync/atomic"
	"testing"

	"unitman/edge/internal/s3"
)

const (
	zipLocalSignature = "PK\x03\x04"
	zipEmptySignature = "PK\x05\x06"
)

type recordedRequest struct {
	Method string
	Path   string
	Header http.Header
	Body   []byte
}

type fakeS3 struct {
	server   *httptest.Server
	requests chan recordedRequest
	calls    atomic.Int64

	respond func(w http.ResponseWriter, r *http.Request)
}

func newFakeS3(t *testing.T) *fakeS3 {
	t.Helper()

	fake := &fakeS3{requests: make(chan recordedRequest, 8)}
	fake.respond = func(w http.ResponseWriter, _ *http.Request) {
		w.WriteHeader(http.StatusOK)
	}

	fake.server = httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		fake.calls.Add(1)

		body, _ := io.ReadAll(r.Body)

		select {
		case fake.requests <- recordedRequest{
			Method: r.Method,
			Path:   r.URL.EscapedPath(),
			Header: r.Header.Clone(),
			Body:   body,
		}:
		default:
		}

		fake.respond(w, r)
	}))

	t.Cleanup(fake.server.Close)

	return fake
}

func (f *fakeS3) nextRequest(t *testing.T) recordedRequest {
	t.Helper()

	select {
	case request := <-f.requests:
		return request
	default:
		t.Fatal("no S3 request was recorded")
		return recordedRequest{}
	}
}

func newMediaServer(t *testing.T, fake *fakeS3, maxBytes int64) *httptest.Server {
	t.Helper()

	store, err := s3.New(s3.Options{
		Endpoint:     fake.server.URL,
		Bucket:       "unitman-mediateka",
		Region:       "us-east-1",
		AccessKey:    "access",
		SecretKey:    "secret",
		UsePathStyle: true,
	})
	if err != nil {
		t.Fatalf("build s3 client: %v", err)
	}

	handler := NewHandler(store, Options{MaxBytes: maxBytes})

	mux := http.NewServeMux()
	mux.HandleFunc("PUT /media/{key...}", handler.Put)
	mux.HandleFunc("GET /media/{key...}", handler.Get)
	mux.HandleFunc("DELETE /media/{key...}", handler.Delete)
	mux.HandleFunc("GET /healthz", handler.Healthz)

	server := httptest.NewServer(mux)
	t.Cleanup(server.Close)

	return server
}

func putRequest(t *testing.T, url string, body io.Reader, contentType string) *http.Response {
	t.Helper()

	request, err := http.NewRequest(http.MethodPut, url, body)
	if err != nil {
		t.Fatalf("build request: %v", err)
	}

	if contentType != "" {
		request.Header.Set("Content-Type", contentType)
	}

	response, err := http.DefaultClient.Do(request)
	if err != nil {
		t.Fatalf("put request: %v", err)
	}

	t.Cleanup(func() { response.Body.Close() })

	return response
}

func TestPutAcceptsBothZipSignatures(t *testing.T) {
	t.Parallel()

	for _, signature := range []string{zipLocalSignature, zipEmptySignature} {
		signature := signature

		t.Run("signature_"+signature, func(t *testing.T) {
			t.Parallel()

			fake := newFakeS3(t)
			server := newMediaServer(t, fake, DefaultMaxBytes)

			payload := signature + "streamed-archive-bytes"

			response := putRequest(t, server.URL+"/media/drafts/a.zip", strings.NewReader(payload), "")

			if response.StatusCode != http.StatusOK {
				t.Fatalf("status = %d, want 200", response.StatusCode)
			}

			var body struct {
				Key  string `json:"key"`
				Size int64  `json:"size"`
			}
			if err := json.NewDecoder(response.Body).Decode(&body); err != nil {
				t.Fatalf("decode response: %v", err)
			}

			if body.Key != "drafts/a.zip" {
				t.Fatalf("key = %q", body.Key)
			}

			if body.Size != int64(len(payload)) {
				t.Fatalf("size = %d, want %d", body.Size, len(payload))
			}

			recorded := fake.nextRequest(t)

			if recorded.Method != http.MethodPut {
				t.Fatalf("method = %q", recorded.Method)
			}

			if recorded.Path != "/unitman-mediateka/drafts/a.zip" {
				t.Fatalf("path = %q", recorded.Path)
			}

			if got := recorded.Header.Get("x-amz-content-sha256"); got != s3.UnsignedPayload {
				t.Fatalf("payload hash = %q", got)
			}

			if got := recorded.Header.Get("Content-Type"); got != "application/zip" {
				t.Fatalf("content type = %q", got)
			}

			if string(recorded.Body) != payload {
				t.Fatalf("streamed body mismatch: %q", string(recorded.Body))
			}
		})
	}
}

func TestPutRejectsNonZipBodyWithoutContactingStorage(t *testing.T) {
	t.Parallel()

	fake := newFakeS3(t)
	server := newMediaServer(t, fake, DefaultMaxBytes)

	response := putRequest(t, server.URL+"/media/drafts/a.zip", strings.NewReader("not-a-zip"), "application/zip")

	if response.StatusCode != http.StatusBadRequest {
		t.Fatalf("status = %d, want 400", response.StatusCode)
	}

	if calls := fake.calls.Load(); calls != 0 {
		t.Fatalf("S3 was called %d times, want 0", calls)
	}
}

func TestPutRejectsShortBody(t *testing.T) {
	t.Parallel()

	fake := newFakeS3(t)
	server := newMediaServer(t, fake, DefaultMaxBytes)

	response := putRequest(t, server.URL+"/media/drafts/a.zip", strings.NewReader("PK"), "")

	if response.StatusCode != http.StatusBadRequest {
		t.Fatalf("status = %d, want 400", response.StatusCode)
	}

	if calls := fake.calls.Load(); calls != 0 {
		t.Fatalf("S3 was called %d times, want 0", calls)
	}
}

func TestPutRejectsKnownOversizeBody(t *testing.T) {
	t.Parallel()

	fake := newFakeS3(t)
	server := newMediaServer(t, fake, 16)

	payload := zipLocalSignature + strings.Repeat("x", 64)
	response := putRequest(t, server.URL+"/media/drafts/a.zip", strings.NewReader(payload), "application/zip")

	if response.StatusCode != http.StatusRequestEntityTooLarge {
		t.Fatalf("status = %d, want 413", response.StatusCode)
	}

	if calls := fake.calls.Load(); calls != 0 {
		t.Fatalf("S3 was called %d times, want 0", calls)
	}
}

func TestPutRejectsChunkedOversizeBody(t *testing.T) {
	t.Parallel()

	fake := newFakeS3(t)
	server := newMediaServer(t, fake, 16)

	payload := zipLocalSignature + strings.Repeat("x", 64)

	request, err := http.NewRequest(http.MethodPut, server.URL+"/media/drafts/a.zip", bytes.NewReader([]byte(payload)))
	if err != nil {
		t.Fatalf("build request: %v", err)
	}

	// Force a chunked upload so the size is only discovered while streaming.
	request.ContentLength = -1

	response, err := http.DefaultClient.Do(request)
	if err != nil {
		t.Fatalf("put request: %v", err)
	}
	defer response.Body.Close()

	if response.StatusCode != http.StatusRequestEntityTooLarge {
		t.Fatalf("status = %d, want 413", response.StatusCode)
	}
}

func TestGetStreamsHeadersAndBody(t *testing.T) {
	t.Parallel()

	fake := newFakeS3(t)
	const objectBody = "PK\x03\x04stored-archive"

	fake.respond = func(w http.ResponseWriter, _ *http.Request) {
		w.Header().Set("Content-Type", "application/zip")
		w.Header().Set("Content-Length", strconv.Itoa(len(objectBody)))
		w.Header().Set("ETag", `"abc123"`)
		w.WriteHeader(http.StatusOK)
		_, _ = io.WriteString(w, objectBody)
	}

	server := newMediaServer(t, fake, DefaultMaxBytes)

	response, err := http.Get(server.URL + "/media/drafts/a.zip")
	if err != nil {
		t.Fatalf("get request: %v", err)
	}
	defer response.Body.Close()

	if response.StatusCode != http.StatusOK {
		t.Fatalf("status = %d", response.StatusCode)
	}

	if got := response.Header.Get("Content-Type"); got != "application/zip" {
		t.Fatalf("content type = %q", got)
	}

	if got := response.Header.Get("ETag"); got != `"abc123"` {
		t.Fatalf("etag = %q", got)
	}

	if got := response.Header.Get("Content-Length"); got != strconv.Itoa(len(objectBody)) {
		t.Fatalf("content length = %q", got)
	}

	body, err := io.ReadAll(response.Body)
	if err != nil {
		t.Fatalf("read body: %v", err)
	}

	if string(body) != objectBody {
		t.Fatalf("body = %q", string(body))
	}
}

func TestGetMissingObjectReturns404(t *testing.T) {
	t.Parallel()

	fake := newFakeS3(t)
	fake.respond = func(w http.ResponseWriter, r *http.Request) {
		http.NotFound(w, r)
	}

	server := newMediaServer(t, fake, DefaultMaxBytes)

	response, err := http.Get(server.URL + "/media/drafts/missing.zip")
	if err != nil {
		t.Fatalf("get request: %v", err)
	}
	defer response.Body.Close()

	if response.StatusCode != http.StatusNotFound {
		t.Fatalf("status = %d, want 404", response.StatusCode)
	}
}

func TestDeleteReturns204And404(t *testing.T) {
	t.Parallel()

	t.Run("deleted", func(t *testing.T) {
		t.Parallel()

		fake := newFakeS3(t)
		fake.respond = func(w http.ResponseWriter, _ *http.Request) {
			w.WriteHeader(http.StatusNoContent)
		}

		server := newMediaServer(t, fake, DefaultMaxBytes)

		request, _ := http.NewRequest(http.MethodDelete, server.URL+"/media/drafts/a.zip", nil)
		response, err := http.DefaultClient.Do(request)
		if err != nil {
			t.Fatalf("delete request: %v", err)
		}
		defer response.Body.Close()

		if response.StatusCode != http.StatusNoContent {
			t.Fatalf("status = %d, want 204", response.StatusCode)
		}

		recorded := fake.nextRequest(t)
		if recorded.Method != http.MethodDelete {
			t.Fatalf("method = %q", recorded.Method)
		}

		if recorded.Path != "/unitman-mediateka/drafts/a.zip" {
			t.Fatalf("path = %q", recorded.Path)
		}
	})

	t.Run("missing", func(t *testing.T) {
		t.Parallel()

		fake := newFakeS3(t)
		fake.respond = func(w http.ResponseWriter, r *http.Request) {
			http.NotFound(w, r)
		}

		server := newMediaServer(t, fake, DefaultMaxBytes)

		request, _ := http.NewRequest(http.MethodDelete, server.URL+"/media/drafts/missing.zip", nil)
		response, err := http.DefaultClient.Do(request)
		if err != nil {
			t.Fatalf("delete request: %v", err)
		}
		defer response.Body.Close()

		if response.StatusCode != http.StatusNotFound {
			t.Fatalf("status = %d, want 404", response.StatusCode)
		}
	})
}

func TestPathKeyDecodingHandlesEscapedSegments(t *testing.T) {
	t.Parallel()

	fake := newFakeS3(t)
	fake.respond = func(w http.ResponseWriter, _ *http.Request) {
		w.Header().Set("Content-Type", "application/zip")
		w.WriteHeader(http.StatusOK)
		_, _ = io.WriteString(w, zipLocalSignature)
	}

	server := newMediaServer(t, fake, DefaultMaxBytes)

	response, err := http.Get(server.URL + "/media/smoke/edge%20test.zip")
	if err != nil {
		t.Fatalf("get request: %v", err)
	}
	defer response.Body.Close()

	if response.StatusCode != http.StatusOK {
		t.Fatalf("status = %d", response.StatusCode)
	}

	recorded := fake.nextRequest(t)

	if recorded.Path != "/unitman-mediateka/smoke/edge%20test.zip" {
		t.Fatalf("path = %q", recorded.Path)
	}
}

func TestKeyValidationRejectsTraversal(t *testing.T) {
	t.Parallel()

	for _, key := range []string{"", "/", "a/../b", "a/./b", "a//b"} {
		request := httptest.NewRequest(http.MethodGet, "/media/x", nil)
		request.SetPathValue("key", key)

		recorder := httptest.NewRecorder()

		if _, ok := keyFromRequest(recorder, request); ok {
			t.Fatalf("key %q was accepted", key)
		}

		if recorder.Code != http.StatusBadRequest {
			t.Fatalf("key %q status = %d, want 400", key, recorder.Code)
		}
	}
}

func TestHealthz(t *testing.T) {
	t.Parallel()

	fake := newFakeS3(t)
	server := newMediaServer(t, fake, DefaultMaxBytes)

	response, err := http.Get(server.URL + "/healthz")
	if err != nil {
		t.Fatalf("health request: %v", err)
	}
	defer response.Body.Close()

	if response.StatusCode != http.StatusOK {
		t.Fatalf("status = %d", response.StatusCode)
	}
}
