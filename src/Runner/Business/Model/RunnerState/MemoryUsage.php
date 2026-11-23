<?php

declare(strict_types=1);

namespace App\Runner\Business\Model\RunnerState;

/**
 * Потребление оперативной памяти хоста раннера.
 *
 * В отличие от [`MemoryInfo`](src/Runner/Business/Model/RunnerState/MemoryInfo.php:5)
 * (сырые значения `/proc/meminfo` из старого health-push) здесь уже
 * рассчитаны использованные байты и процент, а также уровень угрозы.
 */
final class MemoryUsage implements \JsonSerializable
{
    public function __construct(
        public int $totalBytes = 0,
        public int $usedBytes = 0,
        public int $availableBytes = 0,
        public float $usedPercent = 0,
        public string $level = 'ok',
    ) {
    }

    public function jsonSerialize(): mixed
    {
        return get_object_vars($this);
    }
}
