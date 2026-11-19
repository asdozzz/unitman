package s3

import (
	"context"
	"errors"
	"fmt"
	"io"
	"net"
	"net/http"
	"net/url"
	"strings"
	"time"
)

// Options configures the S3-compatible object storage client.
type Options struct {
	Endpoint     string
	Bucket       string
	Region       string
	AccessKey    string
	SecretKey    string
	UsePathStyle bool
	HTTPClient   *http.Client
	Now          func() time.Time
}

// StatusError is returned when S3 answers with a non-success status code.
type StatusError struct {
	StatusCode int
	Body       string
}

func (e *StatusError) Error() string {
	if e.Body == "" {
		return fmt.Sprintf("s3 returned HTTP %d", e.StatusCode)
	}

	return fmt.Sprintf("s3 returned HTTP %d: %s", e.StatusCode, e.Body)
}

// Client is a minimal S3 client supporting streaming PUT, GET, HEAD and DELETE.
// It deliberately avoids an external SDK: only the SigV4 subset needed by the
// media gateway is implemented.
type Client struct {
	baseURL    *url.URL
	bucket     string
	signer     Signer
	pathStyle  bool
	httpClient *http.Client
	now        func() time.Time
}

// New validates the options and builds a client.
func New(options Options) (*Client, error) {
	if strings.TrimSpace(options.Endpoint) == "" {
		return nil, errors.New("s3 endpoint is required")
	}

	if strings.TrimSpace(options.Bucket) == "" {
		return nil, errors.New("s3 bucket is required")
	}

	endpoint, err := url.Parse(strings.TrimRight(options.Endpoint, "/"))
	if err != nil {
		return nil, fmt.Errorf("parse s3 endpoint: %w", err)
	}

	if endpoint.Scheme != "http" && endpoint.Scheme != "https" {
		return nil, fmt.Errorf("s3 endpoint must be an http(s) URL, got %q", options.Endpoint)
	}

	if endpoint.Host == "" {
		return nil, fmt.Errorf("s3 endpoint has no host: %q", options.Endpoint)
	}

	httpClient := options.HTTPClient
	if httpClient == nil {
		httpClient = newHTTPClient()
	}

	now := options.Now
	if now == nil {
		now = time.Now
	}

	return &Client{
		baseURL:    endpoint,
		bucket:     options.Bucket,
		signer:     NewSigner(options.AccessKey, options.SecretKey, options.Region),
		pathStyle:  options.UsePathStyle,
		httpClient: httpClient,
		now:        now,
	}, nil
}

func newHTTPClient() *http.Client {
	transport := &http.Transport{
		Proxy: http.ProxyFromEnvironment,
		DialContext: (&net.Dialer{
			Timeout:   10 * time.Second,
			KeepAlive: 30 * time.Second,
		}).DialContext,
		MaxIdleConns:          20,
		MaxIdleConnsPerHost:   10,
		IdleConnTimeout:       90 * time.Second,
		TLSHandshakeTimeout:   10 * time.Second,
		ExpectContinueTimeout: 1 * time.Second,
		ResponseHeaderTimeout: 60 * time.Second,
	}

	// No global timeout: uploads and downloads stream large objects and are
	// bounded by the caller context instead.
	return &http.Client{Transport: transport}
}

// Put streams body to the object key. A negative size means "unknown", which
// makes Go send a chunked body; a known size is forwarded as Content-Length.
func (c *Client) Put(ctx context.Context, key string, body io.Reader, size int64, contentType string) error {
	headers := make(http.Header)
	extra := make([]string, 0, 1)

	if contentType != "" {
		headers.Set("Content-Type", contentType)
		extra = append(extra, "content-type")
	}

	request, err := c.newRequest(ctx, http.MethodPut, key, body, size, UnsignedPayload, headers, extra)
	if err != nil {
		return err
	}

	response, err := c.httpClient.Do(request)
	if err != nil {
		return fmt.Errorf("put s3 object: %w", err)
	}
	defer response.Body.Close()

	snippet, _ := io.ReadAll(io.LimitReader(response.Body, 512))

	if response.StatusCode < 200 || response.StatusCode >= 300 {
		return &StatusError{StatusCode: response.StatusCode, Body: strings.TrimSpace(string(snippet))}
	}

	return nil
}

// Get starts a streamed download. The caller owns response.Body and must close
// it. Non-2xx statuses are returned as-is so callers can map 404 to a client
// error.
func (c *Client) Get(ctx context.Context, key string) (*http.Response, error) {
	request, err := c.newRequest(ctx, http.MethodGet, key, nil, 0, EmptyPayloadSHA256, nil, nil)
	if err != nil {
		return nil, err
	}

	response, err := c.httpClient.Do(request)
	if err != nil {
		return nil, fmt.Errorf("get s3 object: %w", err)
	}

	return response, nil
}

// Head reports the status code and headers of an object.
func (c *Client) Head(ctx context.Context, key string) (int, http.Header, error) {
	request, err := c.newRequest(ctx, http.MethodHead, key, nil, 0, EmptyPayloadSHA256, nil, nil)
	if err != nil {
		return 0, nil, err
	}

	response, err := c.httpClient.Do(request)
	if err != nil {
		return 0, nil, fmt.Errorf("head s3 object: %w", err)
	}
	defer response.Body.Close()

	_, _ = io.Copy(io.Discard, io.LimitReader(response.Body, 4*1024))

	return response.StatusCode, response.Header.Clone(), nil
}

// Delete removes an object and returns the S3 status code (204 or 404).
func (c *Client) Delete(ctx context.Context, key string) (int, error) {
	request, err := c.newRequest(ctx, http.MethodDelete, key, nil, 0, EmptyPayloadSHA256, nil, nil)
	if err != nil {
		return 0, err
	}

	response, err := c.httpClient.Do(request)
	if err != nil {
		return 0, fmt.Errorf("delete s3 object: %w", err)
	}
	defer response.Body.Close()

	_, _ = io.Copy(io.Discard, io.LimitReader(response.Body, 4*1024))

	return response.StatusCode, nil
}

func (c *Client) newRequest(
	ctx context.Context,
	method string,
	key string,
	body io.Reader,
	size int64,
	payloadHash string,
	headers http.Header,
	extraSigned []string,
) (*http.Request, error) {
	host, rawPath := c.target(key)

	request, err := http.NewRequestWithContext(ctx, method, c.baseURL.Scheme+"://"+host+rawPath, body)
	if err != nil {
		return nil, fmt.Errorf("build s3 request: %w", err)
	}

	// req.Host drives the Host header, which participates in the signature.
	request.Host = host

	if body != nil {
		request.ContentLength = size
	}

	for name, values := range headers {
		for _, value := range values {
			request.Header.Add(name, value)
		}
	}

	c.signer.Sign(request, payloadHash, c.now(), extraSigned...)

	return request, nil
}

func (c *Client) target(key string) (host string, rawPath string) {
	normalizedKey := strings.TrimPrefix(strings.TrimSpace(key), "/")
	encodedKey := encodePath(normalizedKey)

	if c.pathStyle {
		return c.baseURL.Host, "/" + encodeSegment(c.bucket) + "/" + encodedKey
	}

	return c.bucket + "." + c.baseURL.Host, "/" + encodedKey
}
