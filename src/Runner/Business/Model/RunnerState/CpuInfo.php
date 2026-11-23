<?php

declare(strict_types=1);

namespace App\Runner\Business\Model\RunnerState;

/**
 * Загрузка CPU хоста раннера.
 *
 * `usagePercent` считается раннером по двум срезам `/proc/stat`,
 * `load*` берётся из `/proc/loadavg`.
 */
final class CpuInfo implements \JsonSerializable
{
    public function __construct(
        public float $usagePercent = 0,
        public int $cores = 0,
        public float $load1 = 0,
        public float $load5 = 0,
        public float $load15 = 0,
    ) {
    }

    public function jsonSerialize(): mixed
    {
        return get_object_vars($this);
    }
}
