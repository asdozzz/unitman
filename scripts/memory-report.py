#!/usr/bin/env python3
"""Сводка памяти по пулам RoadRunner.

Соединяет два источника:
  * CSV из scripts/memory-snapshot.sh — pid, rss, pss, hwm;
  * вывод `rr workers -c .rr.yaml` — принадлежность pid к пулу.

Использование:
  python3 scripts/memory-report.py <snapshot.csv> <rr-workers.txt>
"""

from __future__ import annotations

import csv
import re
import sys
from collections import defaultdict

SECTION_RE = re.compile(r"^Workers of \[(?P<section>[^\]]+)\]:")
ROW_RE = re.compile(r"^\│\s*(?P<pid>\d+)\s*│")


def parse_workers(path: str) -> dict[int, str]:
    pools: dict[int, str] = {}
    section = "unknown"
    with open(path, encoding="utf-8") as handle:
        for line in handle:
            match = SECTION_RE.match(line)
            if match:
                section = match.group("section")
                continue
            match = ROW_RE.match(line)
            if match:
                pools[int(match.group("pid"))] = section
    return pools


def parse_snapshot(path: str) -> dict[int, dict[str, int]]:
    rows: dict[int, dict[str, int]] = {}
    with open(path, encoding="utf-8", newline="") as handle:
        for row in csv.DictReader(handle):
            pid = int(row["pid"])
            rows[pid] = {
                "rss_kb": int(row["rss_kb"]),
                "pss_kb": int(row["pss_kb"]),
                "hwm_kb": int(row["hwm_kb"]),
            }
    return rows


def main() -> int:
    if len(sys.argv) != 3:
        print(__doc__)
        return 1

    pools = parse_workers(sys.argv[2])
    snapshot = parse_snapshot(sys.argv[1])

    stats: dict[str, dict[str, float]] = defaultdict(
        lambda: {"count": 0, "rss_kb": 0, "pss_kb": 0, "hwm_kb": 0}
    )
    unknown: list[int] = []

    for pid, values in snapshot.items():
        pool = pools.get(pid)
        if pool is None:
            pool = "unmapped"
            unknown.append(pid)
        entry = stats[pool]
        entry["count"] += 1
        entry["rss_kb"] += values["rss_kb"]
        entry["pss_kb"] += values["pss_kb"]
        entry["hwm_kb"] = max(entry["hwm_kb"], values["hwm_kb"])

    total = {"count": 0, "rss_kb": 0, "pss_kb": 0, "hwm_kb": 0}

    print(f"{'pool':<12} {'count':>6} {'rss_mb':>10} {'pss_mb':>10} {'avg_pss':>10} {'hwm_mb':>10}")
    for pool in sorted(stats):
        entry = stats[pool]
        print(
            f"{pool:<12} {int(entry['count']):>6} "
            f"{entry['rss_kb'] / 1024:>10.1f} {entry['pss_kb'] / 1024:>10.1f} "
            f"{entry['pss_kb'] / 1024 / entry['count']:>10.1f} {entry['hwm_kb'] / 1024:>10.1f}"
        )
        for key in total:
            if key == "hwm_kb":
                total[key] = max(total[key], entry[key])
            else:
                total[key] += entry[key]

    print(
        f"{'TOTAL':<12} {int(total['count']):>6} "
        f"{total['rss_kb'] / 1024:>10.1f} {total['pss_kb'] / 1024:>10.1f} "
        f"{'':>10} {total['hwm_kb'] / 1024:>10.1f}"
    )

    if unknown:
        print(f"\nUnmapped PIDs: {', '.join(map(str, sorted(unknown)))}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
