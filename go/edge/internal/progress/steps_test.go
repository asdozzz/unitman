package progress

import (
	"encoding/json"
	"strings"
	"testing"
)

func marshalSteps(t *testing.T, steps []*orderedStep) string {
	t.Helper()

	encoded, err := json.Marshal(steps)
	if err != nil {
		t.Fatalf("marshal steps: %v", err)
	}

	return string(encoded)
}

func TestDecodeStepsNormalization(t *testing.T) {
	t.Parallel()

	cases := []struct {
		name    string
		content string
		want    string
	}{
		{
			name:    "pascal case digit string",
			content: `[{"Unixtime":100,"UnixtimeStart":"50"}]`,
			want:    `[{"Unixtime":100,"UnixtimeStart":50}]`,
		},
		{
			name:    "camel case",
			content: `[{"unixtime":100,"unixtimeStart":50}]`,
			want:    `[{"unixtime":100,"UnixtimeStart":50}]`,
		},
		{
			name:    "both spellings prefer pascal",
			content: `[{"unixtimeStart":10,"Command":"x","UnixtimeStart":20,"Unixtime":30}]`,
			want:    `[{"Command":"x","Unixtime":30,"UnixtimeStart":20}]`,
		},
		{
			name:    "negative start dropped",
			content: `[{"Unixtime":100,"UnixtimeStart":-1}]`,
			want:    `[{"Unixtime":100}]`,
		},
		{
			name:    "start later than finish dropped",
			content: `[{"Unixtime":100,"UnixtimeStart":101}]`,
			want:    `[{"Unixtime":100}]`,
		},
		{
			name:    "start equal to finish kept",
			content: `[{"Unixtime":100,"UnixtimeStart":100}]`,
			want:    `[{"Unixtime":100,"UnixtimeStart":100}]`,
		},
		{
			name:    "non numeric start dropped",
			content: `[{"Unixtime":100,"UnixtimeStart":"abc"}]`,
			want:    `[{"Unixtime":100}]`,
		},
		{
			name:    "float start dropped",
			content: `[{"Unixtime":100,"UnixtimeStart":50.0}]`,
			want:    `[{"Unixtime":100}]`,
		},
		{
			name:    "boolean start dropped",
			content: `[{"Unixtime":100,"UnixtimeStart":true}]`,
			want:    `[{"Unixtime":100}]`,
		},
		{
			name:    "string finish is parsed",
			content: `[{"Unixtime":"100","UnixtimeStart":"50"}]`,
			want:    `[{"Unixtime":"100","UnixtimeStart":50}]`,
		},
		{
			name:    "camel finish is parsed",
			content: `[{"unixtime":100,"UnixtimeStart":99}]`,
			want:    `[{"unixtime":100,"UnixtimeStart":99}]`,
		},
		{
			name:    "no start key keeps payload untouched",
			content: `[{"Command":"x","Response":"ok","Unixtime":100}]`,
			want:    `[{"Command":"x","Response":"ok","Unixtime":100}]`,
		},
		{
			name:    "other fields preserve raw representation",
			content: `[{"Command":"x","Meta":{"n":1.50},"Unixtime":100,"UnixtimeStart":5}]`,
			want:    `[{"Command":"x","Meta":{"n":1.50},"Unixtime":100,"UnixtimeStart":5}]`,
		},
	}

	for _, tc := range cases {
		tc := tc

		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()

			steps, stepError := DecodeSteps(tc.content, DefaultLimits())
			if stepError != nil {
				t.Fatalf("DecodeSteps error: %v", stepError)
			}

			if got := marshalSteps(t, steps); got != tc.want {
				t.Fatalf("normalized = %s, want %s", got, tc.want)
			}
		})
	}
}

func TestDecodeStepsDropsNonObjectElements(t *testing.T) {
	t.Parallel()

	content := `[{"Command":"a"},42,"str",null,[],true,{"Command":"b"}]`

	steps, stepError := DecodeSteps(content, DefaultLimits())
	if stepError != nil {
		t.Fatalf("DecodeSteps error: %v", stepError)
	}

	const want = `[{"Command":"a"},{"Command":"b"}]`
	if got := marshalSteps(t, steps); got != want {
		t.Fatalf("normalized = %s, want %s", got, want)
	}
}

func TestDecodeStepsEmptyContent(t *testing.T) {
	t.Parallel()

	steps, stepError := DecodeSteps("", DefaultLimits())
	if stepError != nil {
		t.Fatalf("DecodeSteps error: %v", stepError)
	}

	if got := marshalSteps(t, steps); got != "[]" {
		t.Fatalf("normalized = %s, want []", got)
	}
}

func TestDecodeStepsRejectsMalformedPayloads(t *testing.T) {
	t.Parallel()

	cases := []string{
		`{}`,
		`null`,
		`5`,
		`"text"`,
		`[`,
		`[{"a":}]`,
		`[]trailing`,
		`[{"a":1}][{"b":2}]`,
	}

	for _, content := range cases {
		content := content

		t.Run(content, func(t *testing.T) {
			t.Parallel()

			_, stepError := DecodeSteps(content, DefaultLimits())
			if stepError == nil || stepError.Code != CodeStepsMalformed {
				t.Fatalf("DecodeSteps(%q) error = %v, want %s", content, stepError, CodeStepsMalformed)
			}
		})
	}
}

func TestDecodeStepsContentSizeLimit(t *testing.T) {
	t.Parallel()

	if DefaultMaxStepsContentBytes != 2560*1024 {
		t.Fatalf("DefaultMaxStepsContentBytes = %d", DefaultMaxStepsContentBytes)
	}

	limits := Limits{
		MaxContentBytes:  3,
		MaxSteps:         DefaultMaxSteps,
		MaxResponseBytes: DefaultMaxStepResponseBytes,
	}

	_, stepError := DecodeSteps(`[{}]`, limits)
	if stepError == nil || stepError.Code != CodeStepsTooLarge {
		t.Fatalf("error = %v, want %s", stepError, CodeStepsTooLarge)
	}
}

func TestDecodeStepsCountLimit(t *testing.T) {
	t.Parallel()

	if DefaultMaxSteps != 5000 {
		t.Fatalf("DefaultMaxSteps = %d", DefaultMaxSteps)
	}

	limits := Limits{
		MaxContentBytes:  DefaultMaxStepsContentBytes,
		MaxSteps:         2,
		MaxResponseBytes: DefaultMaxStepResponseBytes,
	}

	_, stepError := DecodeSteps(`[{},{},{}]`, limits)
	if stepError == nil || stepError.Code != CodeStepsTooMany {
		t.Fatalf("error = %v, want %s", stepError, CodeStepsTooMany)
	}

	steps, stepError := DecodeSteps(`[{},{}]`, limits)
	if stepError != nil {
		t.Fatalf("two steps rejected: %v", stepError)
	}

	if len(steps) != 2 {
		t.Fatalf("steps = %d, want 2", len(steps))
	}
}

func TestDecodeStepsResponseSizeLimit(t *testing.T) {
	t.Parallel()

	if DefaultMaxStepResponseBytes != 65536 {
		t.Fatalf("DefaultMaxStepResponseBytes = %d", DefaultMaxStepResponseBytes)
	}

	limits := Limits{
		MaxContentBytes:  DefaultMaxStepsContentBytes,
		MaxSteps:         DefaultMaxSteps,
		MaxResponseBytes: 3,
	}

	_, stepError := DecodeSteps(`[{"Response":"abcd"}]`, limits)
	if stepError == nil || stepError.Code != CodeStepTooLarge {
		t.Fatalf("error = %v, want %s", stepError, CodeStepTooLarge)
	}

	if _, stepError := DecodeSteps(`[{"Response":"abc"}]`, limits); stepError != nil {
		t.Fatalf("exact limit rejected: %v", stepError)
	}

	// Non-string Response values are not the heavy field the guard targets.
	if _, stepError := DecodeSteps(`[{"Response":{"big":true}}]`, limits); stepError != nil {
		t.Fatalf("non-string Response rejected: %v", stepError)
	}
}

func TestDecodeStepsLargeResponseMeasuredInBytes(t *testing.T) {
	t.Parallel()

	limits := Limits{
		MaxContentBytes:  DefaultMaxStepsContentBytes,
		MaxSteps:         DefaultMaxSteps,
		MaxResponseBytes: 5,
	}

	// "привет" is 12 bytes, not 6 runes.
	content := `[{"Response":"` + strings.Repeat("п", 6) + `"}]`

	if _, stepError := DecodeSteps(content, limits); stepError == nil || stepError.Code != CodeStepTooLarge {
		t.Fatalf("error = %v, want %s", stepError, CodeStepTooLarge)
	}
}
