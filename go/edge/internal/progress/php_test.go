package progress

import (
	"errors"
	"testing"
)

func TestSerializePHPString(t *testing.T) {
	t.Parallel()

	cases := []struct {
		name  string
		value string
		want  string
	}{
		{name: "ascii", value: "hello", want: `s:5:"hello";`},
		{name: "empty", value: "", want: `s:0:"";`},
		{name: "utf8 byte length", value: "привет", want: `s:12:"привет";`},
		{name: "milliseconds", value: "1730000000123", want: `s:13:"1730000000123";`},
		{name: "payload containing quote terminator", value: `{"a":"b\";c"}`, want: `s:13:"{"a":"b\";c"}";`},
	}

	for _, tc := range cases {
		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()

			if got := SerializePHPString(tc.value); got != tc.want {
				t.Fatalf("SerializePHPString(%q) = %q, want %q", tc.value, got, tc.want)
			}
		})
	}
}

func TestUnserializePHPStringRoundTrip(t *testing.T) {
	t.Parallel()

	values := []string{
		"",
		"hello",
		"привет",
		"1730000000123",
		`{"token":"job-1","steps":[]}`,
		`{"a":"b\";c"}`,
		"line\nwith\nnewlines",
	}

	for _, value := range values {
		value := value

		t.Run(value, func(t *testing.T) {
			t.Parallel()

			serialized := SerializePHPString(value)

			decoded, err := UnserializePHPString(serialized)
			if err != nil {
				t.Fatalf("UnserializePHPString(%q) error: %v", serialized, err)
			}

			if decoded != value {
				t.Fatalf("round trip = %q, want %q", decoded, value)
			}
		})
	}
}

func TestUnserializePHPStringRejectsMalformedValues(t *testing.T) {
	t.Parallel()

	invalid := []string{
		"",
		"hello",
		`s:5:"hi";`,
		`s:abc:"hello";`,
		`s:3:"hello";`,
		`s:5:hello;`,
		`s:5:"hello"`,
		`i:10;`,
		`s:5:"hello";trailing`,
	}

	for _, raw := range invalid {
		raw := raw

		t.Run(raw, func(t *testing.T) {
			t.Parallel()

			if _, err := UnserializePHPString(raw); !errors.Is(err, ErrNotPHPString) {
				t.Fatalf("UnserializePHPString(%q) error = %v, want ErrNotPHPString", raw, err)
			}
		})
	}
}

func TestUnwrapPHPStringFallsBackToRaw(t *testing.T) {
	t.Parallel()

	if got := UnwrapPHPString(`s:2:"hi";`); got != "hi" {
		t.Fatalf("serialized unwrap = %q, want %q", got, "hi")
	}

	if got := UnwrapPHPString(`{"plain":true}`); got != `{"plain":true}` {
		t.Fatalf("plain fallback = %q", got)
	}
}
