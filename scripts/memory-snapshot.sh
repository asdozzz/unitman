#!/bin/sh
#
# Снимок памяти PHP/RoadRunner-процессов внутри контейнера web.
#
# Использование:
#   sh scripts/memory-snapshot.sh [output.csv]
#
# Скрипт читает /proc, классифицирует процессы по роли и печатает сводку.
# RSS суммируется с двойным учётом разделяемых страниц, поэтому дополнительно
# собирается PSS из smaps_rollup — он точнее показывает вклад процесса.
#
# Формат CSV: timestamp,pid,ppid,rss_kb,pss_kb,hwm_kb,role,cmd
#
set -u

OUT="${1:-/tmp/memory-snapshot.csv}"
TS=$(date -u +%Y-%m-%dT%H:%M:%SZ)

: > "$OUT"
echo "timestamp,pid,ppid,rss_kb,pss_kb,hwm_kb,role,cmd" >> "$OUT"

for d in /proc/[0-9]*; do
    pid=${d#/proc/}
    [ -r "$d/status" ] || continue

    cmd=$(tr '\0' ' ' < "$d/cmdline" 2>/dev/null | sed 's/ *$//')
    [ -z "$cmd" ] && continue

    role=""
    case "$cmd" in
        *"rr serve"*)                    role="roadrunner" ;;
        *"public/index.php"*)            role="http" ;;
        *"temporal:workflow:runtime"*)   role="temporal" ;;
        *"app:service:start"*)           role="service" ;;
        *"bin/console"*)                 role="console" ;;
        *)                               continue ;;
    esac

    rss=$(awk '/^VmRSS:/{print $2}' "$d/status" 2>/dev/null)
    hwm=$(awk '/^VmHWM:/{print $2}' "$d/status" 2>/dev/null)
    ppid=$(awk '/^PPid:/{print $2}' "$d/status" 2>/dev/null)
    pss=$(awk '/^Pss:/{print $2}' "$d/smaps_rollup" 2>/dev/null)
    [ -z "$pss" ] && pss=${rss:-0}
    cmd_clean=$(printf '%s' "$cmd" | tr ',' ' ')

    echo "$TS,$pid,${ppid:-0},${rss:-0},${pss:-0},${hwm:-0},$role,$cmd_clean" >> "$OUT"
done

echo "=== PHP/RoadRunner processes: role summary ==="
awk -F',' '
    NR > 1 {
        count[$7]++
        rss[$7] += $4
        pss[$7] += $5
        if ($6 > hwm[$7]) hwm[$7] = $6
    }
    END {
        printf "%-12s %6s %10s %10s %10s %10s\n", "role", "count", "rss_mb", "pss_mb", "avg_pss", "hwm_mb"
        for (k in count) {
            printf "%-12s %6d %10.1f %10.1f %10.1f %10.1f\n", k, count[k], rss[k] / 1024, pss[k] / 1024, pss[k] / 1024 / count[k], hwm[k] / 1024
        }
    }
' "$OUT" | sort

echo
echo "=== totals ==="
awk -F',' '
    NR > 1 {
        count++
        rss += $4
        pss += $5
        if ($6 > hwm) hwm = $6
    }
    END {
        printf "processes=%d rss=%.1fMB pss=%.1fMB max_hwm=%.1fMB\n", count, rss / 1024, pss / 1024, hwm / 1024
    }
' "$OUT"

echo
echo "=== service processes ==="
awk -F',' 'NR > 1 && $7 == "service" {print $2, $4, $5, $8}' "$OUT"

echo
echo "CSV: $OUT"
