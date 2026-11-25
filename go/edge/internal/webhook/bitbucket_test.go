package webhook

import (
	"net/http"
	"testing"
)

func TestBitbucketSignatureVerification(t *testing.T) {
	body := []byte(`{"push":{"changes":[]}}`)

	handler, err := NewHandler(Options{PHPBaseURL: "http://php:8080", BitbucketSecret: "bitbucket-secret"})
	if err != nil {
		t.Fatalf("build handler: %v", err)
	}

	valid := "sha256=" + signHex("bitbucket-secret", body)
	if !handler.verify(ProviderBitbucket, http.Header{"X-Hub-Signature": []string{valid}}, body) {
		t.Fatal("valid Bitbucket signature must pass")
	}

	if handler.verify(ProviderBitbucket, http.Header{"X-Hub-Signature": []string{"sha256=deadbeef"}}, body) {
		t.Fatal("invalid Bitbucket signature must fail")
	}
}

func TestDetectBitbucketProvider(t *testing.T) {
	for _, header := range []http.Header{
		{"X-Event-Key": []string{"repo:push"}},
		{"X-Hub-Signature": []string{"sha256=abc"}},
	} {
		provider, ok := detectProvider(header)
		if !ok {
			t.Fatal("Bitbucket provider must be detected")
		}

		if provider != ProviderBitbucket {
			t.Fatalf("provider = %s, want %s", provider, ProviderBitbucket)
		}
	}
}
