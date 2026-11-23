<?php

declare(strict_types=1);

namespace App\Runner\Business\Model\RunnerState;

/**
 * Снимок ресурсов сервера раннера из health-push.
 *
 * Уровни (`level` у памяти, дисков и общий) и признак блокировки считает
 * раннер по порогам 70% / 85% / 90% потребления. Backend не пересчитывает
 * проценты, а только хранит снимок и показывает его администраторам.
 */
final class RunnerResources implements \JsonSerializable
{
    /**
     * @param array<int, array<string, mixed>> $disks
     */
    public function __construct(
        public string $hostname = '',
        public int $uptimeSeconds = 0,
        public int $collectedAt = 0,
        public CpuInfo $cpu = new CpuInfo(),
        public MemoryUsage $memory = new MemoryUsage(),
        public array $disks = [],
        public ?DockerDiskUsage $docker = null,
        public string $level = 'ok',
        public bool $blocked = false,
        public ?string $blockReason = null,
    ) {
    }

    /**
     * Достигнут ли «чёрный» уровень. Раннер уже присылает `blocked`, но
     * проверяем и `level`: так блокировка работает и при частичном payload.
     */
    public function isBlocker(): bool
    {
        return $this->blocked || $this->level === 'blocker';
    }

    public function jsonSerialize(): mixed
    {
        return get_object_vars($this);
    }
}
