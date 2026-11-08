<?php

declare(strict_types=1);

namespace App\Unitman\Business\Command\VolumeType;

use App\Utils\Converter\JsonBodySerializableInterface;

/**
 * Удаление типа тома из каталога проекта.
 */
final class UdalitTipVoluma implements JsonBodySerializableInterface
{
    public function __construct(
        public readonly string $projectId = '',
        public readonly string $code = ''
    ) {
    }
}
