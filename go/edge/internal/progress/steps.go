package progress

import (
	"bytes"
	"encoding/json"
	"errors"
	"io"
	"math"
	"strconv"
	"strings"
)

const (
	DefaultMaxStepsContentBytes = 2560 * 1024
	DefaultMaxSteps             = 5000
	DefaultMaxStepResponseBytes = 65536
)

const (
	CodeStepsTooLarge  = "runner.steps_too_large"
	CodeStepsTooMany   = "runner.steps_too_many"
	CodeStepTooLarge   = "runner.step_too_large"
	CodeStepsMalformed = "runner.steps_malformed"
)

// Limits are the snapshot guard rails mirrored from RunnerController.
type Limits struct {
	MaxContentBytes  int
	MaxSteps         int
	MaxResponseBytes int
}

// DefaultLimits returns the same values as the PHP controller constants.
func DefaultLimits() Limits {
	return Limits{
		MaxContentBytes:  DefaultMaxStepsContentBytes,
		MaxSteps:         DefaultMaxSteps,
		MaxResponseBytes: DefaultMaxStepResponseBytes,
	}
}

// StepError is a transport-agnostic rejection reason; the HTTP handler maps
// Code to a status code and to the public error envelope.
type StepError struct {
	Code string
}

func (e *StepError) Error() string {
	return e.Code
}

// orderedStep keeps the original key order and raw JSON values of a step.
// PHP decodes to an associative array, unsets normalized keys and appends the
// canonical key at the end, which is exactly what this type reproduces while
// preserving the raw representation of every other field.
type orderedStep struct {
	keys []string
	vals map[string]json.RawMessage
}

// MarshalJSON emits fields in their original order with untouched raw values.
func (o *orderedStep) MarshalJSON() ([]byte, error) {
	var b bytes.Buffer
	b.Grow(64)
	b.WriteByte('{')

	for i, key := range o.keys {
		if i > 0 {
			b.WriteByte(',')
		}

		encodedKey, err := json.Marshal(key)
		if err != nil {
			return nil, err
		}

		b.Write(encodedKey)
		b.WriteByte(':')
		b.Write(o.vals[key])
	}

	b.WriteByte('}')

	return b.Bytes(), nil
}

func (o *orderedStep) set(key string, value json.RawMessage) {
	if _, ok := o.vals[key]; !ok {
		o.keys = append(o.keys, key)
	}

	o.vals[key] = value
}

func (o *orderedStep) delete(key string) {
	if _, ok := o.vals[key]; !ok {
		return
	}

	delete(o.vals, key)

	for i, existing := range o.keys {
		if existing == key {
			o.keys = append(o.keys[:i], o.keys[i+1:]...)
			break
		}
	}
}

// DecodeSteps decodes stepsContent exactly once, keeps only JSON objects,
// normalizes UnixtimeStart/unixtimeStart and enforces the size limits.
func DecodeSteps(stepsContent string, limits Limits) ([]*orderedStep, *StepError) {
	if limits.MaxContentBytes > 0 && len(stepsContent) > limits.MaxContentBytes {
		return nil, &StepError{Code: CodeStepsTooLarge}
	}

	steps := make([]*orderedStep, 0)

	if stepsContent == "" {
		return steps, nil
	}

	decoder := json.NewDecoder(strings.NewReader(stepsContent))

	token, err := decoder.Token()
	if err != nil {
		return nil, &StepError{Code: CodeStepsMalformed}
	}

	delim, ok := token.(json.Delim)
	if !ok || delim != '[' {
		return nil, &StepError{Code: CodeStepsMalformed}
	}

	for decoder.More() {
		var raw json.RawMessage
		if err := decoder.Decode(&raw); err != nil {
			return nil, &StepError{Code: CodeStepsMalformed}
		}

		trimmed := bytes.TrimSpace(raw)
		// The contract asks for an array of objects; anything else is dropped.
		if len(trimmed) == 0 || trimmed[0] != '{' {
			continue
		}

		step, err := parseOrderedStep(trimmed)
		if err != nil {
			return nil, &StepError{Code: CodeStepsMalformed}
		}

		normalizeStep(step)

		if limits.MaxResponseBytes > 0 {
			if err := assertStepResponseSize(step, limits.MaxResponseBytes); err != nil {
				return nil, err
			}
		}

		steps = append(steps, step)

		if limits.MaxSteps > 0 && len(steps) > limits.MaxSteps {
			return nil, &StepError{Code: CodeStepsTooMany}
		}
	}

	if _, err := decoder.Token(); err != nil {
		return nil, &StepError{Code: CodeStepsMalformed}
	}

	token, err = decoder.Token()
	if !errors.Is(err, io.EOF) || token != nil {
		return nil, &StepError{Code: CodeStepsMalformed}
	}

	return steps, nil
}

func parseOrderedStep(raw []byte) (*orderedStep, error) {
	decoder := json.NewDecoder(bytes.NewReader(raw))

	token, err := decoder.Token()
	if err != nil {
		return nil, err
	}

	delim, ok := token.(json.Delim)
	if !ok || delim != '{' {
		return nil, errors.New("step is not an object")
	}

	step := &orderedStep{vals: make(map[string]json.RawMessage)}

	for decoder.More() {
		keyToken, err := decoder.Token()
		if err != nil {
			return nil, err
		}

		key, ok := keyToken.(string)
		if !ok {
			return nil, errors.New("object key is not a string")
		}

		var value json.RawMessage
		if err := decoder.Decode(&value); err != nil {
			return nil, err
		}

		// PHP arrays keep the first insertion position but the last value.
		step.set(key, value)
	}

	if _, err := decoder.Token(); err != nil {
		return nil, err
	}

	if token, err := decoder.Token(); !errors.Is(err, io.EOF) || token != nil {
		return nil, errors.New("trailing data after step object")
	}

	return step, nil
}

// normalizeStep mirrors RunnerController::normalizeStep. Both spellings are
// removed first; the canonical key is appended only when the value is a
// non-negative integer (or digits-only string) and not later than the finish.
func normalizeStep(step *orderedStep) {
	var rawStart json.RawMessage

	found := false

	for _, key := range []string{"UnixtimeStart", "unixtimeStart"} {
		value, ok := step.vals[key]
		if !ok {
			continue
		}

		if !found {
			rawStart = value
			found = true
		}

		step.delete(key)
	}

	if !found {
		return
	}

	start, ok := parseStartValue(rawStart)
	if !ok || start < 0 {
		return
	}

	if finish, ok := stepFinishValue(step); ok && start > finish {
		return
	}

	step.set("UnixtimeStart", json.RawMessage(strconv.FormatInt(start, 10)))
}

func assertStepResponseSize(step *orderedStep, maxBytes int) *StepError {
	raw, ok := step.vals["Response"]
	if !ok {
		return nil
	}

	var response string
	if err := json.Unmarshal(raw, &response); err != nil {
		// Non-string values are not the heavy field the guard targets.
		return nil
	}

	if len(response) > maxBytes {
		return &StepError{Code: CodeStepTooLarge}
	}

	return nil
}

// parseStartValue accepts an int or a digits-only string, matching PHP's
// is_int() || (is_string() && ctype_digit()) check.
func parseStartValue(raw json.RawMessage) (int64, bool) {
	trimmed := bytes.TrimSpace(raw)
	if len(trimmed) == 0 {
		return 0, false
	}

	if trimmed[0] == '"' {
		var value string
		if err := json.Unmarshal(trimmed, &value); err != nil {
			return 0, false
		}

		return parseDigitsString(value)
	}

	return parseJSONInteger(string(trimmed))
}

// stepFinishValue mirrors `$step['Unixtime'] ?? $step['unixtime'] ?? null`:
// the next key is consulted when the current one is missing or JSON null.
func stepFinishValue(step *orderedStep) (int64, bool) {
	for _, key := range []string{"Unixtime", "unixtime"} {
		raw, ok := step.vals[key]
		if !ok {
			continue
		}

		trimmed := bytes.TrimSpace(raw)
		if len(trimmed) == 0 || bytes.Equal(trimmed, []byte("null")) {
			continue
		}

		if len(trimmed) > 0 && trimmed[0] == '"' {
			var value string
			if err := json.Unmarshal(trimmed, &value); err != nil {
				return 0, false
			}

			return parseDigitsString(value)
		}

		return parseJSONInteger(string(trimmed))
	}

	return 0, false
}

func parseDigitsString(value string) (int64, bool) {
	if !isASCIIDigits(value) {
		return 0, false
	}

	parsed, err := strconv.ParseInt(value, 10, 64)
	if err != nil {
		if errors.Is(err, strconv.ErrRange) {
			// PHP casts huge numeric strings to PHP_INT_MAX.
			return math.MaxInt64, true
		}

		return 0, false
	}

	return parsed, true
}

// parseJSONInteger accepts only JSON integer literals; floats such as 1.0 or
// 1e3 decode to PHP float and are therefore ignored by the PHP normalizer.
func parseJSONInteger(value string) (int64, bool) {
	if value == "" {
		return 0, false
	}

	i := 0
	if value[0] == '-' {
		i++
		if i == len(value) {
			return 0, false
		}
	}

	if value[i] == '0' {
		if i != len(value)-1 {
			return 0, false
		}
	} else {
		for ; i < len(value); i++ {
			if value[i] < '0' || value[i] > '9' {
				return 0, false
			}
		}
	}

	parsed, err := strconv.ParseInt(value, 10, 64)
	if err != nil {
		if errors.Is(err, strconv.ErrRange) {
			if strings.HasPrefix(value, "-") {
				return math.MinInt64, true
			}

			return math.MaxInt64, true
		}

		return 0, false
	}

	return parsed, true
}
