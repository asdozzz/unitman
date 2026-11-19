<?php

declare(strict_types=1);

namespace App\Runner\Business\Utils;

use App\Runner\Business\Model\RunnerState\DockerContainerStats;
use App\Runner\Business\Model\RunnerState\MemoryInfo;

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
