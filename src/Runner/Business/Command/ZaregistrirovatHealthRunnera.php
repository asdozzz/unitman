<?php

declare(strict_types=1);

namespace App\Runner\Business\Command;

use App\Utils\Converter\JsonBodySerializableInterface;

/**
 * Health-push от Golang-раннера.
 *
 * `dockerStats` — построчный вывод `docker stats --format json`,
 * `memInfo` — содержимое `/proc/meminfo`. Оба поля необязательны.
 */
final class ZaregistrirovatHealthRunnera implements JsonBodySerializableInterface
{
    public function __construct(
        public readonly string $runnerId,
        public readonly string $taskQueue,
        public readonly bool $active = true,
        public readonly ?string $dockerStats = null,
        public readonly ?string $memInfo = null,
        public readonly ?int $unixtime = null,
    ) {
    }
}
