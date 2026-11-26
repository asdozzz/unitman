<?php

declare(strict_types=1);

namespace App\Runner\Infra\Adapter;

use App\Runner\Business\Model\RunnerState;
use App\Runner\Business\Port\RunnerSelector;

/**
 * Реализация выбора раннера по контракту `plans/runner-multi-runner.md` §6.
 *
 * Кандидат обязан иметь публичный адрес (`publicAddress`): раннер без адреса
 * может присылать health, но не участвует в сборках и построении ссылок.
 *
 * Score (больше — лучше):
 * - свободная память — вес 0.5;
 * - свободный CPU — вес 0.3;
 * - свободный диск / меньший Docker footprint — вес 0.2.
 *
 * Tie-break (детерминизм для тестов): меньше привязанных юнитов, затем
 * лексикографически меньший `runnerId`.
 *
 * Класс остаётся чистым: список кандидатов и число привязанных юнитов
 * передаются снаружи. Загрузку данных выполняет
 * [`RunnerSelectionService`](src/Runner/Business/UseCase/RunnerSelectionService.php:31),
 * поэтому алгоритм воспроизводим в unit-тестах без БД.
 */
final class DefaultRunnerSelector implements RunnerSelector
{
    private const HEALTH_TTL_SECONDS = 180;

    private const DOCKER_FREE_BASELINE_BYTES = 100 * 1024 * 1024 * 1024;

    public function select(array $runners, array $boundUnitsByRunner = []): RunnerState
    {
        $candidates = array_values(array_filter(
            $runners,
            fn (RunnerState $runner): bool => $this->isLive($runner),
        ));

        if ($candidates === []) {
            throw new \DomainException('runner.not_found');
        }

        usort(
            $candidates,
            function (RunnerState $a, RunnerState $b) use ($boundUnitsByRunner): int {
                $byScore = $this->score($b) <=> $this->score($a);
                if ($byScore !== 0) {
                    return $byScore;
                }

                $boundA = $boundUnitsByRunner[$a->getId()] ?? 0;
                $boundB = $boundUnitsByRunner[$b->getId()] ?? 0;
                if ($boundA !== $boundB) {
                    return $boundA <=> $boundB;
                }

                // Последний tie-break — лексикографически меньший runnerId.
                return $a->getId() <=> $b->getId();
            },
        );

        return $candidates[0];
    }

    private function isLive(RunnerState $runner): bool
    {
        return $runner->isActive()
            && $runner->hasPublicAddress()
            && $runner->isHealthFresh(self::HEALTH_TTL_SECONDS)
            && !$runner->isResourcesBlocked();
    }

    private function score(RunnerState $runner): float
    {
        $freeMemory = $runner->getMemoryInfo()->getFreePercent();
        $resources = $runner->getResources();

        if ($resources !== null && $resources->memory->totalBytes > 0) {
            // Структурированный снимок точнее старого /proc/meminfo.
            $freeMemory = max($freeMemory, 100.0 - $resources->memory->usedPercent);
        }

        $cpuUsage = $resources?->cpu->usagePercent ?? 0.0;
        $freeCpu = max(0.0, 100.0 - $cpuUsage);

        $freeDisk = $this->freeDiskPercent($runner);

        return $freeMemory * 0.5 + $freeCpu * 0.3 + $freeDisk * 0.2;
    }

    /**
     * Свободное место: минимум по всем дискам (самый загруженный определяет
     * риск), иначе — оценка по Docker footprint от условного baseline.
     */
    private function freeDiskPercent(RunnerState $runner): float
    {
        $resources = $runner->getResources();

        if ($resources === null) {
            return 100.0;
        }

        $freeDiskPercent = null;

        foreach ($resources->disks as $disk) {
            $usedPercent = $this->diskUsedPercent($disk);
            if ($usedPercent === null) {
                continue;
            }

            $free = max(0.0, 100.0 - $usedPercent);
            $freeDiskPercent = $freeDiskPercent === null ? $free : min($freeDiskPercent, $free);
        }

        if ($freeDiskPercent !== null) {
            return $freeDiskPercent;
        }

        $dockerBytes = $resources->docker?->totalBytes ?? 0;

        return max(
            0.0,
            100.0 - ((float) $dockerBytes / (float) self::DOCKER_FREE_BASELINE_BYTES) * 100.0,
        );
    }

    /**
     * @param array<string, mixed> $disk
     */
    private function diskUsedPercent(array $disk): ?float
    {
        $usedPercent = $disk['usedPercent'] ?? null;
        if (is_numeric($usedPercent)) {
            return (float) $usedPercent;
        }

        $total = $disk['totalBytes'] ?? null;
        $available = $disk['availableBytes'] ?? null;

        if (is_numeric($total) && is_numeric($available) && (float) $total > 0.0) {
            return 100.0 - ((float) $available * 100.0 / (float) $total);
        }

        return null;
    }
}
