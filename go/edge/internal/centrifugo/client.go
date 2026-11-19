package centrifugo

import (
	"bytes"
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"net/http"
	"time"
)

const requestTimeout = 5 * time.Second

// Options are the Centrifugo HTTP API settings.
type Options struct {
	Endpoint string
	APIKey   string
}

// Client publishes progress snapshots through Centrifugo's HTTP API.
type Client struct {
	endpoint   string
	apiKey     string
	httpClient *http.Client
}

// New creates a Centrifugo API client.
func New(options Options) *Client {
	return &Client{
		endpoint: options.Endpoint,
		apiKey:   options.APIKey,
		httpClient: &http.Client{
			Timeout: requestTimeout,
		},
	}
}

type publishParams struct {
	Channel string          `json:"channel"`
	Data    json.RawMessage `json:"data"`
}

type publishRequest struct {
	Method string        `json:"method"`
	Params publishParams `json:"params"`
}

// Publish posts {"method":"publish","params":{"channel":...,"data":...}} and
// treats any non-2xx response as an error.
func (c *Client) Publish(ctx context.Context, channel string, data []byte) error {
	if c.endpoint == "" {
		return errors.New("centrifugo endpoint is not configured")
	}

	body, err := json.Marshal(publishRequest{
		Method: "publish",
		Params: publishParams{
			Channel: channel,
			Data:    json.RawMessage(data),
		},
	})
	if err != nil {
		return fmt.Errorf("encode centrifugo publish request: %w", err)
	}

	request, err := http.NewRequestWithContext(ctx, http.MethodPost, c.endpoint, bytes.NewReader(body))
	if err != nil {
		return fmt.Errorf("build centrifugo request: %w", err)
	}

	request.Header.Set("Content-Type", "application/json")
	request.Header.Set("X-API-Key", c.apiKey)

	response, err := c.httpClient.Do(request)
	if err != nil {
		return fmt.Errorf("call centrifugo: %w", err)
	}
	defer response.Body.Close()

	_, _ = io.Copy(io.Discard, response.Body)

	if response.StatusCode < 200 || response.StatusCode >= 300 {
		return fmt.Errorf("centrifugo returned HTTP %d", response.StatusCode)
	}

	return nil
}
