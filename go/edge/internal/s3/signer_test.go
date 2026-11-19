package s3

import (
	"net/http"
	"net/url"
	"strings"
	"testing"
	"time"
)

// TestSignKnownAnswerAWSDoc reproduces the "Example: GET Object" request from
// the AWS Signature Version 4 documentation. The expected signature is taken
// from the docs, so this is a true known-answer vector.
func TestSignKnownAnswerAWSDoc(t *testing.T) {
	t.Parallel()

	request, err := http.NewRequest(http.MethodGet, "https://examplebucket.s3.amazonaws.com/test.txt", nil)
	if err != nil {
		t.Fatalf("build request: %v", err)
	}
	request.Header.Set("Range", "bytes=0-9")

	signer := NewSigner(
		"AKIAIOSFODNN7EXAMPLE",
		"wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY",
		"us-east-1",
	)

	signer.Sign(request, EmptyPayloadSHA256, time.Date(2013, 5, 24, 0, 0, 0, 0, time.UTC), "range")

	const want = "AWS4-HMAC-SHA256 Credential=AKIAIOSFODNN7EXAMPLE/20130524/us-east-1/s3/aws4_request," +
		" SignedHeaders=host;range;x-amz-content-sha256;x-amz-date," +
		" Signature=f0e8bdb87c964420e857bd35b5d6ed310bd44f0170aba48dd91039c6036bdb41"

	if got := request.Header.Get("Authorization"); got != want {
		t.Fatalf("authorization mismatch\n got: %s\nwant: %s", got, want)
	}

	if got := request.Header.Get("x-amz-content-sha256"); got != EmptyPayloadSHA256 {
		t.Fatalf("x-amz-content-sha256 = %q, want %q", got, EmptyPayloadSHA256)
	}

	if got := request.Header.Get("x-amz-date"); got != "20130524T000000Z" {
		t.Fatalf("x-amz-date = %q", got)
	}
}

func TestCanonicalURIEncodesPathSegments(t *testing.T) {
	t.Parallel()

	u, err := url.Parse("http://minio:9000/unitman-mediateka/units/abc/файл 1+2.zip")
	if err != nil {
		t.Fatalf("parse url: %v", err)
	}

	want := "/unitman-mediateka/units/abc/%D1%84%D0%B0%D0%B9%D0%BB%201%2B2.zip"
	if got := canonicalURI(u); got != want {
		t.Fatalf("canonical uri = %q, want %q", got, want)
	}
}

func TestCanonicalQueryStringIsSortedAndEncoded(t *testing.T) {
	t.Parallel()

	u, err := url.Parse("http://minio:9000/bucket/key?b=2&a=1&a=0&empty=&sp=a%20b")
	if err != nil {
		t.Fatalf("parse url: %v", err)
	}

	want := "a=0&a=1&b=2&empty=&sp=a%20b"
	if got := canonicalQueryString(u); got != want {
		t.Fatalf("canonical query = %q, want %q", got, want)
	}
}

func TestSignStreamingPutUsesUnsignedPayload(t *testing.T) {
	t.Parallel()

	request, err := http.NewRequest(http.MethodPut, "http://minio:9000/unitman-mediateka/drafts/a.zip", strings.NewReader("payload"))
	if err != nil {
		t.Fatalf("build request: %v", err)
	}

	signer := NewSigner("access", "secret", "us-east-1")
	signer.Sign(request, UnsignedPayload, time.Date(2024, 1, 2, 3, 4, 5, 0, time.UTC))

	if got := request.Header.Get("x-amz-content-sha256"); got != UnsignedPayload {
		t.Fatalf("payload hash = %q, want %q", got, UnsignedPayload)
	}

	authorization := request.Header.Get("Authorization")
	if !strings.Contains(authorization, "SignedHeaders=host;x-amz-content-sha256;x-amz-date") {
		t.Fatalf("unexpected signed headers: %s", authorization)
	}

	if !strings.Contains(authorization, "Credential=access/20240102/us-east-1/s3/aws4_request") {
		t.Fatalf("unexpected credential scope: %s", authorization)
	}
}
