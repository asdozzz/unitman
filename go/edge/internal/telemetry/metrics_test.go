package telemetry

import (
	"context"
	"errors"
	"io"
	"net/http"
	"net/http/httptest"
	"strings"
	"sync/atomic"
	"testing"
)

type fakeDocker struct {
	containers []Container
	stats      map[string]Stats
	statsErr   map[string]error
	listErr    error
	statsCalls atomic.Int32
}

func (f *fakeDocker) ListContainers(_ context.Context) ([]Container, error) {
	if f.listErr != nil {
		return nil, f.listErr
	}

	return f.containers, nil
}

func (f *fakeDocker) ContainerStats(_ context.Context, id string) (Stats, error) {
	f.statsCalls.Add(1)

	if err, ok := f.statsErr[id]; ok {
		return Stats{}, err
	}

	return f.stats[id], nil
}

func statsWith(usage, limit, cache uint64, cpuTotal, preCPUTotal, systemTotal, preSystemTotal uint64, onlineCPUs uint32) Stats {
	var stats Stats
	stats.MemoryStats.Usage = usage
	stats.MemoryStats.Limit = limit
	stats.MemoryStats.Stats.Cache = &cache
	stats.CPUStats.CPUUsage.TotalUsage = cpuTotal
	stats.PreCPUStats.CPUUsage.TotalUsage = preCPUTotal
	stats.CPUStats.SystemCPUUsage = systemTotal
	stats.PreCPUStats.SystemCPUUsage = preSystemTotal
	stats.CPUStats.OnlineCPUs = onlineCPUs

	return stats
}

func scrape(t *testing.T, handler *Handler) string {
	t.Helper()

	recorder := httptest.NewRecorder()
	handler.Metrics(recorder, httptest.NewRequest(http.MethodGet, "/metrics", nil))

	if recorder.Code != http.StatusOK {
		t.Fatalf("status = %d, want 200", recorder.Code)
	}

	return recorder.Body.String()
}

func TestMetricsExposeMemoryCPULimitAndCounts(t *testing.T) {
	t.Parallel()

	client := &fakeDocker{
		containers: []Container{
			{ID: "web", Names: []string{"/unitman-web-1"}},
			{ID: "worker", Names: []string{"/unitman-web-worker-1"}},
			{ID: "temporal", Names: []string{"/unitman-web-temporal-1"}},
			{ID: "postgres", Names: []string{"/unitman-postgres-1"}},
		},
		stats: map[string]Stats{
			// 1000 usage - 200 cache = 800; cpu: (100/1000)*2*100 = 20%.
			"web": statsWith(1000, 4096, 200, 200_000_000, 100_000_000, 2_000_000_000, 1_000_000_000, 2),
			// No cache subtraction effect: cpu delta 0 -> 0%.
			"worker":   statsWith(2048, 8192, 0, 500, 500, 2000, 1000, 1),
			"temporal": statsWith(512, 1024, 0, 300, 100, 2000, 1000, 4),
			"postgres": statsWith(64, 128, 0, 0, 0, 0, 0, 0),
		},
		statsErr: map[string]error{},
	}

	body := scrape(t, NewHandler(client, Options{}))

	required := []string{
		`unitman_container_memory_bytes{name="unitman-web-1",role="web"} 800`,
		`unitman_container_memory_limit_bytes{name="unitman-web-1",role="web"} 4096`,
		`unitman_container_cpu_percent{name="unitman-web-1",role="web"} 20`,
		`unitman_container_memory_bytes{name="unitman-web-worker-1",role="web-worker"} 2048`,
		`unitman_container_memory_bytes{name="unitman-web-temporal-1",role="web-temporal"} 512`,
		`unitman_container_memory_bytes{name="unitman-postgres-1",role="other"} 64`,
		`unitman_container_count{role="web"} 1`,
		`unitman_container_count{role="web-worker"} 1`,
		`unitman_container_count{role="web-temporal"} 1`,
		`unitman_container_count{role="other"} 1`,
	}

	for _, line := range required {
		if !strings.Contains(body, line) {
			t.Fatalf("metrics output is missing %q\n%s", line, body)
		}
	}

	if !strings.Contains(body, "# TYPE unitman_container_memory_bytes gauge") {
		t.Fatalf("missing TYPE header\n%s", body)
	}
}

func TestMetricsPreferComposeServiceLabel(t *testing.T) {
	t.Parallel()

	client := &fakeDocker{
		containers: []Container{
			{
				ID:     "custom",
				Names:  []string{"/unitman-custom-1"},
				Labels: map[string]string{composeServiceLabel: "web"},
			},
		},
		stats: map[string]Stats{
			"custom": statsWith(10, 20, 0, 0, 0, 0, 0, 0),
		},
		statsErr: map[string]error{},
	}

	body := scrape(t, NewHandler(client, Options{}))

	if !strings.Contains(body, `unitman_container_memory_bytes{name="unitman-custom-1",role="web"} 10`) {
		t.Fatalf("compose label was not used\n%s", body)
	}
}

func TestMetricsRespectPrefixFilter(t *testing.T) {
	t.Parallel()

	client := &fakeDocker{
		containers: []Container{
			{ID: "a", Names: []string{"/unitman-web-1"}},
			{ID: "b", Names: []string{"/other-service-1"}},
		},
		stats: map[string]Stats{
			"a": statsWith(1, 2, 0, 0, 0, 0, 0, 0),
			"b": statsWith(3, 4, 0, 0, 0, 0, 0, 0),
		},
		statsErr: map[string]error{},
	}

	body := scrape(t, NewHandler(client, Options{Prefix: "unitman-"}))

	if !strings.Contains(body, `name="unitman-web-1"`) {
		t.Fatalf("expected unitman container\n%s", body)
	}

	if strings.Contains(body, "other-service-1") {
		t.Fatalf("prefix filter did not exclude other-service-1\n%s", body)
	}

	if calls := client.statsCalls.Load(); calls != 1 {
		t.Fatalf("stats calls = %d, want 1", calls)
	}
}

func TestMetricsSkipContainersWithStatsErrors(t *testing.T) {
	t.Parallel()

	client := &fakeDocker{
		containers: []Container{
			{ID: "a", Names: []string{"/unitman-web-1"}},
			{ID: "b", Names: []string{"/unitman-web-worker-1"}},
		},
		stats: map[string]Stats{
			"a": statsWith(10, 20, 0, 0, 0, 0, 0, 0),
		},
		statsErr: map[string]error{
			"b": errors.New("stats unavailable"),
		},
	}

	body := scrape(t, NewHandler(client, Options{}))

	if !strings.Contains(body, `name="unitman-web-1"`) {
		t.Fatalf("healthy container is missing\n%s", body)
	}

	if strings.Contains(body, `name="unitman-web-worker-1"`) {
		t.Fatalf("failed container should be skipped\n%s", body)
	}
}

func TestMetricsDockerUnavailable(t *testing.T) {
	t.Parallel()

	client := &fakeDocker{listErr: errors.New("socket missing")}

	recorder := httptest.NewRecorder()
	NewHandler(client, Options{}).Metrics(recorder, httptest.NewRequest(http.MethodGet, "/metrics", nil))

	if recorder.Code != http.StatusBadGateway {
		t.Fatalf("status = %d, want 502", recorder.Code)
	}
}

func TestHealthz(t *testing.T) {
	t.Parallel()

	recorder := httptest.NewRecorder()
	NewHandler(&fakeDocker{}, Options{}).Healthz(recorder, httptest.NewRequest(http.MethodGet, "/healthz", nil))

	if recorder.Code != http.StatusOK {
		t.Fatalf("status = %d, want 200", recorder.Code)
	}
}

func TestDeriveRole(t *testing.T) {
	t.Parallel()

	tests := []struct {
		name   string
		labels map[string]string
		want   string
	}{
		{name: "unitman-web-1", want: "web"},
		{name: "/unitman-web-1", want: "web"},
		{name: "unitman-web-worker-1", want: "web-worker"},
		{name: "unitman-web-temporal-1", want: "web-temporal"},
		{name: "unitman-postgres-1", want: "other"},
		{name: "web", want: "web"},
		{name: "unitman-web-1", labels: map[string]string{composeServiceLabel: "web-worker"}, want: "web-worker"},
		{name: "weird-name", labels: map[string]string{}, want: "other"},
	}

	for _, test := range tests {
		test := test

		t.Run(test.name+"_"+test.want, func(t *testing.T) {
			t.Parallel()

			if got := deriveRole(test.name, test.labels); got != test.want {
				t.Fatalf("deriveRole(%q) = %q, want %q", test.name, got, test.want)
			}
		})
	}
}

func TestMemoryUsageWithoutCache(t *testing.T) {
	t.Parallel()

	var stats Stats
	stats.MemoryStats.Usage = 4096
	stats.MemoryStats.Limit = 8192

	if got := memoryUsage(stats); got != 4096 {
		t.Fatalf("memory usage = %d", got)
	}
}

func TestCPUPercentGuards(t *testing.T) {
	t.Parallel()

	if got := cpuPercent(Stats{}); got != 0 {
		t.Fatalf("cpu percent for empty stats = %v", got)
	}

	stats := statsWith(0, 0, 0, 100, 200, 1000, 500, 0)
	if got := cpuPercent(stats); got != 0 {
		t.Fatalf("cpu percent for negative delta = %v", got)
	}
}

func TestMetricsContentType(t *testing.T) {
	t.Parallel()

	recorder := httptest.NewRecorder()
	handler := NewHandler(&fakeDocker{stats: map[string]Stats{}, statsErr: map[string]error{}}, Options{})
	handler.Metrics(recorder, httptest.NewRequest(http.MethodGet, "/metrics", nil))

	contentType := recorder.Header().Get("Content-Type")
	if !strings.HasPrefix(contentType, "text/plain") {
		t.Fatalf("content type = %q", contentType)
	}

	body, _ := io.ReadAll(recorder.Body)

	if !strings.Contains(string(body), "# HELP unitman_container_count") {
		t.Fatalf("missing count help\n%s", body)
	}
}
