package progress

import "testing"

func TestComputeSignatureKnownVector(t *testing.T) {
	t.Parallel()

	// RFC-style HMAC-SHA256 vector for key "key" and the concatenated message
	// "The quick brown fox jumps over the lazy dog".
	const want = "f7bc83f430538424b13298e6aa6fb143ef4d59a14946175997479dbc2d1a3cd8"

	got := ComputeSignature(
		"key",
		"The quick brown fox jumps over the lazy ",
		"dog",
	)

	if got != want {
		t.Fatalf("ComputeSignature = %q, want %q", got, want)
	}
}

func TestVerifySignature(t *testing.T) {
	t.Parallel()

	const (
		secret       = "runner-secret"
		responseID   = "job-777"
		stepsContent = `[{"Command":"docker build"}]`
	)

	valid := ComputeSignature(secret, responseID, stepsContent)

	cases := []struct {
		name      string
		secret    string
		signature string
		want      bool
	}{
		{name: "valid", secret: secret, signature: valid, want: true},
		{name: "missing", secret: secret, signature: "", want: false},
		{name: "wrong secret", secret: "other", signature: valid, want: false},
		{name: "tampered steps", secret: secret, signature: ComputeSignature(secret, responseID, stepsContent+" "), want: false},
		{name: "uppercase rejected", secret: secret, signature: "F7BC83F430538424B13298E6AA6FB143EF4D59A14946175997479DBC2D1A3CD8", want: false},
		{name: "empty secret bypasses missing", secret: "", signature: "", want: true},
		{name: "empty secret bypasses garbage", secret: "", signature: "garbage", want: true},
	}

	for _, tc := range cases {
		tc := tc

		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()

			if got := VerifySignature(tc.secret, responseID, stepsContent, tc.signature); got != tc.want {
				t.Fatalf("VerifySignature() = %v, want %v", got, tc.want)
			}
		})
	}
}
