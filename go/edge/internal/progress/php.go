package progress

import (
	"errors"
	"strconv"
	"strings"
)

// ErrNotPHPString is returned when a raw KV value is not a PHP-serialized
// string of the canonical form s:<byte_len>:"<payload>";
var ErrNotPHPString = errors.New("value is not a PHP-serialized string")

// SerializePHPString encodes s exactly like PHP's serialize() does for a
// string. The length prefix is the number of bytes, not characters, which is
// critical for UTF-8 payloads produced by json_encode() on the PHP side.
func SerializePHPString(s string) string {
	var b strings.Builder
	b.Grow(len(s) + 16)
	b.WriteString("s:")
	b.WriteString(strconv.Itoa(len(s)))
	b.WriteString(`:"`)
	b.WriteString(s)
	b.WriteString(`";`)
	return b.String()
}

// UnserializePHPString decodes s:<byte_len>:"<payload>"; without using the
// payload length as a search hint: the byte length is authoritative, so
// payloads containing `";` are handled correctly.
func UnserializePHPString(raw string) (string, error) {
	if !strings.HasPrefix(raw, "s:") {
		return "", ErrNotPHPString
	}

	rest := raw[2:]
	idx := strings.IndexByte(rest, ':')
	if idx <= 0 {
		return "", ErrNotPHPString
	}

	lengthToken := rest[:idx]
	if !isASCIIDigits(lengthToken) {
		return "", ErrNotPHPString
	}

	n, err := strconv.Atoi(lengthToken)
	if err != nil || n < 0 {
		return "", ErrNotPHPString
	}

	tail := rest[idx+1:]
	if len(tail) < 3 || tail[0] != '"' {
		return "", ErrNotPHPString
	}

	if len(tail) != n+3 || tail[1+n:] != `";` {
		return "", ErrNotPHPString
	}

	return tail[1 : 1+n], nil
}

// UnwrapPHPString is a tolerant reader for KV values that may have been
// written by PHP (serialized) or by an operator (plain string).
func UnwrapPHPString(raw string) string {
	if value, err := UnserializePHPString(raw); err == nil {
		return value
	}

	return raw
}

func isASCIIDigits(s string) bool {
	if s == "" {
		return false
	}

	for i := 0; i < len(s); i++ {
		if s[i] < '0' || s[i] > '9' {
			return false
		}
	}

	return true
}
