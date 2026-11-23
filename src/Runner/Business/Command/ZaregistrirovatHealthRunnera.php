<?php

declare(strict_types=1);

namespace App\Runner\Business\Command;

use App\Utils\Converter\JsonBodySerializableInterface;

/**
 * Health-push от Golang-раннера.
 *
 * `dockerStats` — построчный вывод `docker stats --format json`,
 * `memInfo` — содержимое `/proc/meminfo`, `resources` — структурированный
 * снимок ресурсов сервера (CPU, память, диски, Docker, уровень угрозы).
 * Все три поля необязательны.
 */
final class ZaregistrirovatHealthRunnera implements JsonBodySerializableInterface
{
    /**
     * @param array<string, mixed>|null $resources
     */
    public function __construct(
        public readonly string $runnerId,
        public readonly string $taskQueue,
        public readonly bool $active = true,
        public readonly ?string $dockerStats = null,
        public readonly ?string $memInfo = null,
        public readonly ?int $unixtime = null,
        public readonly ?array $resources = null,
    ) {
    }
}
