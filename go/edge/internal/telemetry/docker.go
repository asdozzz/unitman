package telemetry

import (
	"context"
	"encoding/json"
	"fmt"
	"io"
	"net"
	"net/http"
	"strings"
	"time"
)

const (
	// DefaultDockerSocket is where the Docker Engine API listens by default.
	DefaultDockerSocket = "/var/run/docker.sock"

	maxDockerResponseBytes = 32 << 20
)

// Container is the subset of GET /containers/json used for metrics.
type Container struct {
	ID     string            `json:"Id"`
	Names  []string          `json:"Names"`
	Labels map[string]string `json:"Labels"`
}

// MemoryStats mirrors memory_stats from GET /containers/{id}/stats.
type MemoryStats struct {
	Usage uint64 `json:"usage"`
	Limit uint64 `json:"limit"`
	Stats struct {
		// Cache is a pointer to distinguish "absent" from "zero".
		Cache *uint64 `json:"cache"`
	} `json:"stats"`
}

// CPUUsage mirrors cpu_stats.cpu_usage.
type CPUUsage struct {
	TotalUsage uint64 `json:"total_usage"`
}

// CPUStats mirrors cpu_stats / precpu_stats.
type CPUStats struct {
	CPUUsage       CPUUsage `json:"cpu_usage"`
	SystemCPUUsage uint64   `json:"system_cpu_usage"`
	OnlineCPUs     uint32   `json:"online_cpus"`
}

// Stats mirrors the subset of GET /containers/{id}/stats used for metrics.
type Stats struct {
	MemoryStats MemoryStats `json:"memory_stats"`
	CPUStats    CPUStats    `json:"cpu_stats"`
	PreCPUStats CPUStats    `json:"precpu_stats"`
}

// DockerClient is the minimal Docker Engine API contract needed by telemetry.
// It is an interface so aggregation can be unit-tested without a daemon.
type DockerClient interface {
	ListContainers(ctx context.Context) ([]Container, error)
	ContainerStats(ctx context.Context, id string) (Stats, error)
}

// UnixClient talks to the Docker Engine API over a Unix socket.
type UnixClient struct {
	client *http.Client
}

// NewUnixClient builds a Docker API client for socketPath (default /var/run/docker.sock).
func NewUnixClient(socketPath string) *UnixClient {
	if strings.TrimSpace(socketPath) == "" {
		socketPath = DefaultDockerSocket
	}

	dialer := &net.Dialer{Timeout: 5 * time.Second}

	transport := &http.Transport{
		DialContext: func(ctx context.Context, _, _ string) (net.Conn, error) {
			return dialer.DialContext(ctx, "unix", socketPath)
		},
		MaxIdleConns:       4,
		IdleConnTimeout:    30 * time.Second,
		DisableCompression: true,
	}

	return &UnixClient{
		client: &http.Client{
			Transport: transport,
			Timeout:   15 * time.Second,
		},
	}
}

// ListContainers returns running containers (all=false).
func (c *UnixClient) ListContainers(ctx context.Context) ([]Container, error) {
	var containers []Container
	if err := c.getJSON(ctx, "/containers/json?all=false", &containers); err != nil {
		return nil, err
	}

	return containers, nil
}

// ContainerStats fetches a one-shot (stream=false) stats snapshot.
func (c *UnixClient) ContainerStats(ctx context.Context, id string) (Stats, error) {
	var stats Stats
	if err := c.getJSON(ctx, "/containers/"+id+"/stats?stream=false", &stats); err != nil {
		return Stats{}, err
	}

	return stats, nil
}

func (c *UnixClient) getJSON(ctx context.Context, path string, target any) error {
	request, err := http.NewRequestWithContext(ctx, http.MethodGet, "http://docker"+path, nil)
	if err != nil {
		return fmt.Errorf("build docker request: %w", err)
	}

	response, err := c.client.Do(request)
	if err != nil {
		return fmt.Errorf("call docker api: %w", err)
	}
	defer response.Body.Close()

	if response.StatusCode < 200 || response.StatusCode >= 300 {
		snippet, _ := io.ReadAll(io.LimitReader(response.Body, 512))

		return fmt.Errorf("docker api %s returned HTTP %d: %s", path, response.StatusCode, strings.TrimSpace(string(snippet)))
	}

	decoder := json.NewDecoder(io.LimitReader(response.Body, maxDockerResponseBytes))
	if err := decoder.Decode(target); err != nil {
		return fmt.Errorf("decode docker response: %w", err)
	}

	return nil
}
