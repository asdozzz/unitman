<?php

declare(strict_types=1);

namespace App\Runner\Business\Utils;

use App\Runner\Business\Model\RunnerState\CpuInfo;
use App\Runner\Business\Model\RunnerState\DockerContainerStats;
use App\Runner\Business\Model\RunnerState\DockerDiskUsage;
use App\Runner\Business\Model\RunnerState\MemoryInfo;
use App\Runner\Business\Model\RunnerState\MemoryUsage;
use App\Runner\Business\Model\RunnerState\RunnerResources;

/**
 * Разбор payload'ов health-push раннера.
 *
 * Поведение повторяет прежний
 * `UstanovitResultatProverkiRabotosposobnostiRunneraUseCase` 1:1:
 * - `dockerStats` — построчный JSON (`docker stats --format json`),
 * - `memInfo` — содержимое `/proc/meminfo`.
 */
final class RunnerHealthPayloadParser
{
    /**
     * @return DockerContainerStats[]
     */
    public function dockerStats(?string $raw): array
    {
        $dockerStats = [];

        if (empty($raw)) {
            return $dockerStats;
        }

        $statsArr = explode("\n", $raw);

        foreach ($statsArr as $stat) {
            $statArr = json_decode($stat, true);

            if (empty($statArr)) {
                continue;
            }

            $dockerStats[] = new DockerContainerStats(
                $statArr['Container'],
                $statArr['Name'],
                $statArr['CPUPerc'],
                $statArr['MemPerc'],
                $statArr['MemUsage'],
                $statArr['NetIO'],
            );
        }

        return $dockerStats;
    }

    /**
     * Разбирает структурированный снимок ресурсов сервера.
     *
     * Раннер уже прислал рассчитанные проценты и уровни, поэтому backend
     * только нормализует типы. `null` — поле не пришло (старый раннер).
     *
     * @param array<string, mixed>|null $raw
     */
    public function resources(?array $raw): ?RunnerResources
    {
        if ($raw === null || $raw === []) {
            return null;
        }

        $cpu = is_array($raw['cpu'] ?? null) ? $raw['cpu'] : [];
        $memory = is_array($raw['memory'] ?? null) ? $raw['memory'] : [];
        $docker = is_array($raw['docker'] ?? null) ? $raw['docker'] : null;

        return new RunnerResources(
            hostname: (string) ($raw['hostname'] ?? ''),
            uptimeSeconds: (int) ($raw['uptimeSeconds'] ?? 0),
            collectedAt: (int) ($raw['collectedAt'] ?? 0),
            cpu: new CpuInfo(
                usagePercent: (float) ($cpu['usagePercent'] ?? 0),
                cores: (int) ($cpu['cores'] ?? 0),
                load1: (float) ($cpu['load1'] ?? 0),
                load5: (float) ($cpu['load5'] ?? 0),
                load15: (float) ($cpu['load15'] ?? 0),
            ),
            memory: new MemoryUsage(
                totalBytes: (int) ($memory['totalBytes'] ?? 0),
                usedBytes: (int) ($memory['usedBytes'] ?? 0),
                availableBytes: (int) ($memory['availableBytes'] ?? 0),
                usedPercent: (float) ($memory['usedPercent'] ?? 0),
                level: (string) ($memory['level'] ?? 'ok'),
            ),
            disks: is_array($raw['disks'] ?? null) ? $raw['disks'] : [],
            docker: $docker === null ? null : new DockerDiskUsage(
                layersBytes: (int) ($docker['layersBytes'] ?? 0),
                containersBytes: (int) ($docker['containersBytes'] ?? 0),
                volumesBytes: (int) ($docker['volumesBytes'] ?? 0),
                buildCacheBytes: (int) ($docker['buildCacheBytes'] ?? 0),
                totalBytes: (int) ($docker['totalBytes'] ?? 0),
                reclaimableBytes: (int) ($docker['reclaimableBytes'] ?? 0),
            ),
            level: (string) ($raw['level'] ?? 'ok'),
            blocked: (bool) ($raw['blocked'] ?? false),
            blockReason: isset($raw['blockReason']) ? (string) $raw['blockReason'] : null,
        );
    }

    public function memoryInfo(?string $raw): MemoryInfo
    {
        if (empty($raw)) {
            return new MemoryInfo(0, 0);
        }

        $memInfoArr = explode("\n", $raw);

        $total = 0;
        $free = 0;

        foreach ($memInfoArr as $memInfoItem) {
            $item = explode(':', $memInfoItem);

            if (empty($item[1])) {
                continue;
            }

            $code = trim($item[0]);
            $value = trim($item[1]);

            if ($code === 'MemTotal') {
                $total = (int) $value;
            }
            if ($code === 'MemAvailable') {
                $free = (int) $value;
            }
        }

        return new MemoryInfo($total, $free);
    }
}
