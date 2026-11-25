package webhook

import (
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"net/http"
	"testing"
)

func signHex(secret string, body []byte) string {
	mac := hmac.New(sha256.New, []byte(secret))
	_, _ = mac.Write(body)

	return hex.EncodeToString(mac.Sum(nil))
}

func TestGiteaSignatureVerification(t *testing.T) {
	body := []byte(`{"ref":"refs/heads/main"}`)

	handler, err := NewHandler(Options{PHPBaseURL: "http://php:8080", GiteaSecret: "gitea-secret"})
	if err != nil {
		t.Fatalf("build handler: %v", err)
	}

	if !handler.verify(ProviderGitea, http.Header{"X-Gitea-Signature": []string{signHex("gitea-secret", body)}}, body) {
		t.Fatal("valid Gitea signature must pass")
	}

	if handler.verify(ProviderGitea, http.Header{"X-Gitea-Signature": []string{"deadbeef"}}, body) {
		t.Fatal("invalid Gitea signature must fail")
	}

	empty, err := NewHandler(Options{PHPBaseURL: "http://php:8080"})
	if err != nil {
		t.Fatalf("build handler: %v", err)
	}

	if !empty.verify(ProviderGitea, http.Header{}, body) {
		t.Fatal("empty secret must disable the check")
	}
}

func TestForgejoSignatureVerification(t *testing.T) {
	body := []byte(`{"ref":"refs/heads/main"}`)

	handler, err := NewHandler(Options{PHPBaseURL: "http://php:8080", ForgejoSecret: "forgejo-secret"})
	if err != nil {
		t.Fatalf("build handler: %v", err)
	}

	valid := signHex("forgejo-secret", body)
	if !handler.verify(ProviderForgejo, http.Header{"X-Forgejo-Signature": []string{valid}}, body) {
		t.Fatal("valid Forgejo signature must pass")
	}

	if handler.verify(ProviderForgejo, http.Header{"X-Forgejo-Signature": []string{valid}}, []byte("other")) {
		t.Fatal("signature for another body must fail")
	}
}

func TestDetectGiteaFamilyProvider(t *testing.T) {
	tests := []struct {
		name   string
		header http.Header
		want   string
	}{
		{name: "forgejo event", header: http.Header{"X-Forgejo-Event": []string{"push"}}, want: ProviderForgejo},
		{name: "forgejo signature", header: http.Header{"X-Forgejo-Signature": []string{"abc"}}, want: ProviderForgejo},
		{name: "gitea event", header: http.Header{"X-Gitea-Event": []string{"push"}}, want: ProviderGitea},
		{name: "gitea signature", header: http.Header{"X-Gitea-Signature": []string{"abc"}}, want: ProviderGitea},
	}

	for _, test := range tests {
		t.Run(test.name, func(t *testing.T) {
			provider, ok := detectProvider(test.header)
			if !ok {
				t.Fatal("provider must be detected")
			}

			if provider != test.want {
				t.Fatalf("provider = %s, want %s", provider, test.want)
			}
		})
	}
}
