package s3

import (
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"net/http"
	"net/url"
	"sort"
	"strings"
	"time"
)

const (
	algorithm     = "AWS4-HMAC-SHA256"
	serviceName   = "s3"
	amzDateFormat = "20060102T150405Z"
	dateFormat    = "20060102"

	// UnsignedPayload is the literal used for streamed PUT bodies: it lets the
	// gateway forward the request body without hashing (and therefore buffering)
	// the whole object.
	UnsignedPayload = "UNSIGNED-PAYLOAD"
)

// EmptyPayloadSHA256 is the SHA-256 of an empty body, required for GET/HEAD/DELETE.
var EmptyPayloadSHA256 = hexSHA256(nil)

// Signer implements AWS Signature Version 4 for S3 requests.
type Signer struct {
	AccessKey string
	SecretKey string
	Region    string
}

// NewSigner builds a signer with the us-east-1 default region.
func NewSigner(accessKey, secretKey, region string) Signer {
	if region == "" {
		region = "us-east-1"
	}

	return Signer{AccessKey: accessKey, SecretKey: secretKey, Region: region}
}

// Sign adds x-amz-date, x-amz-content-sha256 and Authorization to req.
//
// The signed headers are always host, x-amz-date and x-amz-content-sha256;
// extra names (for example content-type or range) are added when present on the
// request. Signing a header that is not sent would break the signature, so the
// extras are read from req.Header after the caller has set them.
func (s Signer) Sign(req *http.Request, payloadHash string, now time.Time, extraSigned ...string) {
	if payloadHash == "" {
		payloadHash = EmptyPayloadSHA256
	}

	amzDate := now.UTC().Format(amzDateFormat)
	dateStamp := now.UTC().Format(dateFormat)

	req.Header.Set("x-amz-date", amzDate)
	req.Header.Set("x-amz-content-sha256", payloadHash)

	headers := map[string]string{
		"host":                 requestHost(req),
		"x-amz-date":           amzDate,
		"x-amz-content-sha256": payloadHash,
	}

	for _, name := range extraSigned {
		normalized := strings.ToLower(strings.TrimSpace(name))
		if normalized == "" || normalized == "host" {
			continue
		}

		value := req.Header.Get(name)
		if value == "" {
			continue
		}

		headers[normalized] = normalizeHeaderValue(value)
	}

	signedHeaderNames := make([]string, 0, len(headers))
	for name := range headers {
		signedHeaderNames = append(signedHeaderNames, name)
	}
	sort.Strings(signedHeaderNames)

	var canonicalHeaders strings.Builder
	for _, name := range signedHeaderNames {
		canonicalHeaders.WriteString(name)
		canonicalHeaders.WriteString(":")
		canonicalHeaders.WriteString(headers[name])
		canonicalHeaders.WriteString("\n")
	}

	signedHeaders := strings.Join(signedHeaderNames, ";")
	canonicalRequest := strings.Join([]string{
		req.Method,
		canonicalURI(req.URL),
		canonicalQueryString(req.URL),
		canonicalHeaders.String(),
		signedHeaders,
		payloadHash,
	}, "\n")

	credentialScope := dateStamp + "/" + s.Region + "/" + serviceName + "/aws4_request"
	stringToSign := strings.Join([]string{
		algorithm,
		amzDate,
		credentialScope,
		hexSHA256([]byte(canonicalRequest)),
	}, "\n")

	signature := hex.EncodeToString(hmacSHA256(s.signingKey(dateStamp), []byte(stringToSign)))

	req.Header.Set("Authorization", algorithm+
		" Credential="+s.AccessKey+"/"+credentialScope+
		", SignedHeaders="+signedHeaders+
		", Signature="+signature)
}

func requestHost(req *http.Request) string {
	if req.Host != "" {
		return req.Host
	}

	return req.URL.Host
}

func (s Signer) signingKey(dateStamp string) []byte {
	dateKey := hmacSHA256([]byte("AWS4"+s.SecretKey), []byte(dateStamp))
	regionKey := hmacSHA256(dateKey, []byte(s.Region))
	serviceKey := hmacSHA256(regionKey, []byte(serviceName))

	return hmacSHA256(serviceKey, []byte("aws4_request"))
}

func canonicalURI(u *url.URL) string {
	if u == nil {
		return "/"
	}

	// Always re-encode from the decoded path: RawPath may hold non-escaped
	// bytes that url.Parse recorded verbatim (unicode, spaces), and SigV4
	// requires the canonical URI to be percent-encoded per segment.
	path := u.Path
	if path == "" {
		return "/"
	}

	if !strings.HasPrefix(path, "/") {
		path = "/" + path
	}

	return encodePath(path)
}

func canonicalQueryString(u *url.URL) string {
	if u == nil || u.RawQuery == "" {
		return ""
	}

	values, err := url.ParseQuery(u.RawQuery)
	if err != nil {
		return ""
	}

	type pair struct {
		key      string
		value    string
		encodedK string
		encodedV string
	}

	pairs := make([]pair, 0)
	for key, list := range values {
		for _, value := range list {
			pairs = append(pairs, pair{
				key:      key,
				value:    value,
				encodedK: encodeSegment(key),
				encodedV: encodeSegment(value),
			})
		}
	}

	sort.Slice(pairs, func(i, j int) bool {
		if pairs[i].encodedK != pairs[j].encodedK {
			return pairs[i].encodedK < pairs[j].encodedK
		}

		return pairs[i].encodedV < pairs[j].encodedV
	})

	encoded := make([]string, 0, len(pairs))
	for _, item := range pairs {
		encoded = append(encoded, item.encodedK+"="+item.encodedV)
	}

	return strings.Join(encoded, "&")
}

func normalizeHeaderValue(value string) string {
	return strings.Join(strings.Fields(value), " ")
}

func encodePath(path string) string {
	segments := strings.Split(path, "/")
	for index, segment := range segments {
		segments[index] = encodeSegment(segment)
	}

	return strings.Join(segments, "/")
}

func encodeSegment(value string) string {
	const hexDigits = "0123456789ABCDEF"

	var builder strings.Builder
	builder.Grow(len(value))

	for index := 0; index < len(value); index++ {
		character := value[index]
		switch {
		case character >= 'A' && character <= 'Z',
			character >= 'a' && character <= 'z',
			character >= '0' && character <= '9',
			character == '-', character == '_', character == '.', character == '~':
			builder.WriteByte(character)
		default:
			builder.WriteByte('%')
			builder.WriteByte(hexDigits[character>>4])
			builder.WriteByte(hexDigits[character&0x0F])
		}
	}

	return builder.String()
}

func hmacSHA256(key, payload []byte) []byte {
	mac := hmac.New(sha256.New, key)
	mac.Write(payload)

	return mac.Sum(nil)
}

func hexSHA256(payload []byte) string {
	sum := sha256.Sum256(payload)

	return hex.EncodeToString(sum[:])
}
