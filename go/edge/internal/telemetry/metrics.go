package telemetry

import (
	"context"
	"log/slog"
	"net/http"
	"sort"
	"strconv"
	"strings"
	"sync"
)

const (
	composeServiceLabel = "com.docker.compose.service"

	// statsConcurrency bounds parallel GET /containers/{id}/stats calls. Each
	// Docker stats call takes about a second, so a sequential scrape of a full
	// stand would exceed any reasonable scrape timeout.
	statsConcurrency = 8
)

var roleSuffixes = []struct {
	suffix string
	role   string
}{
	{suffix: "-web-worker", role: "web-worker"},
	{suffix: "-web-temporal", role: "web-temporal"},
	{suffix: "-web", role: "web"},
}

// Options configures the telemetry metrics handler.
type Options struct {
	// Prefix filters containers by name prefix; empty means all containers.
	Prefix string
	Logger *slog.Logger
}

// Handler exposes Docker container metrics in Prometheus text format.
type Handler struct {
	client DockerClient
	prefix string
	logger *slog.Logger
}

// NewHandler wires the telemetry handler with defaults.
func NewHandler(client DockerClient, options Options) *Handler {
	logger := options.Logger
	if logger == nil {
		logger = slog.Default()
	}

	return &Handler{
		client: client,
		prefix: strings.TrimSpace(options.Prefix),
		logger: logger,
	}
}

type containerMetric struct {
	name       string
	role       string
	memory     uint64
	memoryMax  uint64
	cpuPercent float64
}

// Metrics handles GET /metrics: it scrapes the Docker Engine API on every call.
func (h *Handler) Metrics(w http.ResponseWriter, r *http.Request) {
	metrics, err := h.collect(r.Context())
	if err != nil {
		h.logger.Error("telemetry scrape failed", "error", err)
		http.Error(w, "docker api unavailable", http.StatusBadGateway)
		return
	}

	w.Header().Set("Content-Type", "text/plain; version=0.0.4; charset=utf-8")
	w.WriteHeader(http.StatusOK)
	_, _ = w.Write(formatMetrics(metrics))
}

// Healthz handles GET /healthz.
func (h *Handler) Healthz(w http.ResponseWriter, _ *http.Request) {
	w.Header().Set("Content-Type", "text/plain; charset=utf-8")
	w.WriteHeader(http.StatusOK)
	_, _ = w.Write([]byte("ok\n"))
}

func (h *Handler) collect(ctx context.Context) ([]containerMetric, error) {
	containers, err := h.client.ListContainers(ctx)
	if err != nil {
		return nil, err
	}

	type target struct {
		container Container
		name      string
	}

	targets := make([]target, 0, len(containers))
	for _, container := range containers {
		name := containerName(container)
		if name == "" {
			continue
		}

		if h.prefix != "" && !strings.HasPrefix(name, h.prefix) {
			continue
		}

		targets = append(targets, target{container: container, name: name})
	}

	metrics := make([]containerMetric, len(targets))
	found := make([]bool, len(targets))

	jobs := make(chan int, len(targets))
	for index := range targets {
		jobs <- index
	}
	close(jobs)

	var workers sync.WaitGroup
	for worker := 0; worker < statsConcurrency; worker++ {
		workers.Add(1)
		go func() {
			defer workers.Done()

			for index := range jobs {
				item := targets[index]

				stats, statsErr := h.client.ContainerStats(ctx, item.container.ID)
				if statsErr != nil {
					h.logger.Warn("container stats failed", "container", item.name, "error", statsErr)
					continue
				}

				metrics[index] = containerMetric{
					name:       item.name,
					role:       deriveRole(item.name, item.container.Labels),
					memory:     memoryUsage(stats),
					memoryMax:  stats.MemoryStats.Limit,
					cpuPercent: cpuPercent(stats),
				}
				found[index] = true
			}
		}()
	}

	workers.Wait()

	result := make([]containerMetric, 0, len(metrics))
	for index, metric := range metrics {
		if found[index] {
			result = append(result, metric)
		}
	}

	sort.Slice(result, func(i, j int) bool {
		return result[i].name < result[j].name
	})

	return result, nil
}

func containerName(container Container) string {
	for _, name := range container.Names {
		trimmed := strings.TrimPrefix(strings.TrimSpace(name), "/")
		if trimmed != "" {
			return trimmed
		}
	}

	return ""
}

// deriveRole prefers the compose service label and otherwise recognizes the
// unitman compose container suffixes (-web-1, -web-worker-1, -web-temporal-1).
func deriveRole(name string, labels map[string]string) string {
	if labels != nil {
		if service := strings.TrimSpace(labels[composeServiceLabel]); service != "" {
			return service
		}
	}

	base := strings.TrimPrefix(strings.TrimSpace(name), "/")

	if index := strings.LastIndex(base, "-"); index > 0 && isDigits(base[index+1:]) {
		base = base[:index]
	}

	for _, candidate := range roleSuffixes {
		if base == strings.TrimPrefix(candidate.suffix, "-") || strings.HasSuffix(base, candidate.suffix) {
			return candidate.role
		}
	}

	return "other"
}

func isDigits(value string) bool {
	if value == "" {
		return false
	}

	for index := 0; index < len(value); index++ {
		if value[index] < '0' || value[index] > '9' {
			return false
		}
	}

	return true
}

// memoryUsage subtracts the page cache when the daemon reports it, matching
// `docker stats` and avoiding inflated RSS on cgroup v1 hosts.
func memoryUsage(stats Stats) uint64 {
	usage := stats.MemoryStats.Usage
	if cache := stats.MemoryStats.Stats.Cache; cache != nil {
		if *cache < usage {
			return usage - *cache
		}

		return 0
	}

	return usage
}

// cpuPercent mirrors the Docker CLI formula:
// (container delta / system delta) * online CPUs * 100.
func cpuPercent(stats Stats) float64 {
	cpuDelta := float64(stats.CPUStats.CPUUsage.TotalUsage) - float64(stats.PreCPUStats.CPUUsage.TotalUsage)
	systemDelta := float64(stats.CPUStats.SystemCPUUsage) - float64(stats.PreCPUStats.SystemCPUUsage)

	if cpuDelta <= 0 || systemDelta <= 0 {
		return 0
	}

	onlineCPUs := float64(stats.CPUStats.OnlineCPUs)
	if onlineCPUs <= 0 {
		onlineCPUs = 1
	}

	return (cpuDelta / systemDelta) * onlineCPUs * 100
}

func formatMetrics(metrics []containerMetric) []byte {
	var builder strings.Builder

	builder.WriteString("# HELP unitman_container_memory_bytes Container memory usage in bytes.\n")
	builder.WriteString("# TYPE unitman_container_memory_bytes gauge\n")
	for _, metric := range metrics {
		builder.WriteString("unitman_container_memory_bytes{")
		builder.WriteString(metricLabels(metric))
		builder.WriteString("} ")
		builder.WriteString(strconv.FormatUint(metric.memory, 10))
		builder.WriteString("\n")
	}

	builder.WriteString("# HELP unitman_container_memory_limit_bytes Container memory limit in bytes.\n")
	builder.WriteString("# TYPE unitman_container_memory_limit_bytes gauge\n")
	for _, metric := range metrics {
		builder.WriteString("unitman_container_memory_limit_bytes{")
		builder.WriteString(metricLabels(metric))
		builder.WriteString("} ")
		builder.WriteString(strconv.FormatUint(metric.memoryMax, 10))
		builder.WriteString("\n")
	}

	builder.WriteString("# HELP unitman_container_cpu_percent Container CPU usage percent.\n")
	builder.WriteString("# TYPE unitman_container_cpu_percent gauge\n")
	for _, metric := range metrics {
		builder.WriteString("unitman_container_cpu_percent{")
		builder.WriteString(metricLabels(metric))
		builder.WriteString("} ")
		builder.WriteString(strconv.FormatFloat(metric.cpuPercent, 'f', -1, 64))
		builder.WriteString("\n")
	}

	counts := make(map[string]int)
	for _, metric := range metrics {
		counts[metric.role]++
	}

	roles := make([]string, 0, len(counts))
	for role := range counts {
		roles = append(roles, role)
	}
	sort.Strings(roles)

	builder.WriteString("# HELP unitman_container_count Number of running containers per role.\n")
	builder.WriteString("# TYPE unitman_container_count gauge\n")
	for _, role := range roles {
		builder.WriteString("unitman_container_count{role=\"")
		builder.WriteString(escapeLabelValue(role))
		builder.WriteString("\"} ")
		builder.WriteString(strconv.Itoa(counts[role]))
		builder.WriteString("\n")
	}

	return []byte(builder.String())
}

func metricLabels(metric containerMetric) string {
	return `name="` + escapeLabelValue(metric.name) + `",role="` + escapeLabelValue(metric.role) + `"`
}

func escapeLabelValue(value string) string {
	replacer := strings.NewReplacer(
		`\`, `\\`,
		`"`, `\"`,
		"\n", `\n`,
	)

	return replacer.Replace(value)
}
