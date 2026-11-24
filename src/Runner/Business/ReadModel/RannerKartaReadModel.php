<?php

declare(strict_types=1);

namespace App\Runner\Business\ReadModel;

use App\Runner\Business\Model\RunnerState\MemoryInfo;
use App\Runner\Business\Model\RunnerState\RunnerResources;

/**
 * Карточка раннера для раздела «Раннеры» (только администраторы).
 *
 * `level` — эффективный уровень с учётом свежести health-push,
 * `blocked` — ресурсоёмкие операции запрещены (уровень `blocker`).
 */
final class RannerKartaReadModel implements \JsonSerializable
{
    public function __construct(
        public string $id,
        public string $taskQueue,
        public bool $active,
        public bool $healthFresh,
        public int $lastHealthAt,
        public string $level,
        public bool $blocked,
        public ?string $blockReason,
        public ?RunnerResources $resources,
        // Легаси-снимок `/proc/meminfo` из старого health-push: позволяет
        // показать память, пока раннер не обновлён до структурированных ресурсов.
        public MemoryInfo $memoryInfo = new MemoryInfo(0, 0),
        // Публичный адрес, заданный администратором (A2).
        public ?string $publicAddress = null,
        // Unix-время регистрации (контракт v2, fallback на lastRegisteredAt).
        public ?int $registeredAt = null,
        // Можно ли выбирать раннер для сборок/ссылок.
        public bool $hasPublicAddress = false,
    ) {
    }

    public function jsonSerialize(): mixed
    {
        return get_object_vars($this);
    }
}
