<?php

declare(strict_types=1);

namespace App\Unitman\Business\Command\VolumeType;

use App\Utils\Converter\JsonBodySerializableInterface;

/**
 * Запрос каталога типов `unitman_volumes` проекта.
 */
final class PoluchitTipyVolumov implements JsonBodySerializableInterface
{
    public function __construct(
        public readonly string $projectId = ''
    ) {
    }
}
