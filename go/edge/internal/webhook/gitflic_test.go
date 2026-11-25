package webhook

import (
	"net/http"
	"testing"
)

func TestGitflicSecretVerification(t *testing.T) {
	body := []byte(`{"event":"MERGE"}`)

	handler, err := NewHandler(Options{PHPBaseURL: "http://php:8080", GitflicSecret: "gitflic-secret"})
	if err != nil {
		t.Fatalf("build handler: %v", err)
	}

	if !handler.verify(ProviderGitflic, http.Header{"X-Gitflic-Token": []string{"gitflic-secret"}}, body) {
		t.Fatal("shared token must pass")
	}

	if handler.verify(ProviderGitflic, http.Header{"X-Gitflic-Token": []string{"wrong"}}, body) {
		t.Fatal("wrong shared token must fail")
	}

	if !handler.verify(ProviderGitflic, http.Header{"X-Gitflic-Signature": []string{signHex("gitflic-secret", body)}}, body) {
		t.Fatal("valid signature must pass")
	}
}

func TestDetectGitflicProvider(t *testing.T) {
	for _, header := range []http.Header{
		{"X-Gitflic-Event": []string{"MERGE"}},
		{"X-Gitflic-Signature": []string{"abc"}},
		{"X-Gitflic-Token": []string{"abc"}},
	} {
		provider, ok := detectProvider(header)
		if !ok {
			t.Fatal("GitFlic provider must be detected")
		}

		if provider != ProviderGitflic {
			t.Fatalf("provider = %s, want %s", provider, ProviderGitflic)
		}
	}
}
