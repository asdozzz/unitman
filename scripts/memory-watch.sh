#!/bin/sh
#
# Наблюдение за памятью контейнеров Unitman во времени.
#
# Использование:
#   sh scripts/memory-watch.sh [duration_seconds] [interval_seconds] [output.csv]
#
# Пишет CSV: timestamp,container,mem_usage,mem_percent,cpu_percent
# Контейнеры, которых нет на стенде (например, edge-профиль), пропускаются.
#
set -u

DURATION="${1:-600}"
INTERVAL="${2:-15}"
OUT="${3:-/tmp/memory-drift.csv}"
CONTAINERS="${MEMORY_WATCH_CONTAINERS:-unitman-web-1 unitman-web-worker-1 unitman-web-temporal-1}"

: > "$OUT"
echo "timestamp,container,mem_usage,mem_percent,cpu_percent" >> "$OUT"

START=$(date +%s)
SAMPLES=0

while [ $(( $(date +%s) - START )) -lt "$DURATION" ]; do
    TS=$(date -u +%Y-%m-%dT%H:%M:%SZ)

    for CONTAINER in $CONTAINERS; do
        LINE=$(docker stats --no-stream --format '{{.MemUsage}}|{{.MemPerc}}|{{.CPUPerc}}' "$CONTAINER" 2>/dev/null) || continue
        [ -z "$LINE" ] && continue
        echo "$TS,$CONTAINER,$(printf '%s' "$LINE" | tr '|' ',')" >> "$OUT"
        SAMPLES=$((SAMPLES + 1))
    done

    sleep "$INTERVAL"
done

echo "samples=$SAMPLES duration=${DURATION}s interval=${INTERVAL}s csv=$OUT"
