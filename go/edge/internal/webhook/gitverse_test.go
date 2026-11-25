package webhook

import (
	"net/http"
	"testing"
)

func TestGitverseSecretVerification(t *testing.T) {
	body := []byte(`{"ref":"refs/heads/main"}`)

	handler, err := NewHandler(Options{PHPBaseURL: "http://php:8080", GitverseSecret: "gitverse-secret"})
	if err != nil {
		t.Fatalf("build handler: %v", err)
	}

	if !handler.verify(ProviderGitverse, http.Header{"Authorization": []string{"Bearer gitverse-secret"}}, body) {
		t.Fatal("Bearer secret must pass")
	}

	if handler.verify(ProviderGitverse, http.Header{"Authorization": []string{"Bearer wrong"}}, body) {
		t.Fatal("wrong Bearer secret must fail")
	}

	if !handler.verify(ProviderGitverse, http.Header{"X-Gitverse-Signature": []string{signHex("gitverse-secret", body)}}, body) {
		t.Fatal("valid signature must pass")
	}
}

func TestDetectGitverseProvider(t *testing.T) {
	for _, header := range []http.Header{
		{"X-Gitverse-Event": []string{"push"}},
		{"X-Gitverse-Signature": []string{"abc"}},
		{"X-Gitverse-Token": []string{"abc"}},
		{"Authorization": []string{"Bearer token"}},
	} {
		provider, ok := detectProvider(header)
		if !ok {
			t.Fatal("GitVerse provider must be detected")
		}

		if provider != ProviderGitverse {
			t.Fatalf("provider = %s, want %s", provider, ProviderGitverse)
		}
	}
}
