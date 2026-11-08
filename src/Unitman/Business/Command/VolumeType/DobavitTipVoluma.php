<?php

declare(strict_types=1);

namespace App\Unitman\Business\Command\VolumeType;

use App\Utils\Converter\JsonBodySerializableInterface;

/**
 * Добавление типа тома в каталог проекта.
 *
 * `code` — публичный ключ типа, он же `dir` тома в конфиге.
 */
final class DobavitTipVoluma implements JsonBodySerializableInterface
{
    public function __construct(
        public readonly string $projectId = '',
        public readonly string $code = '',
        public readonly string $name = ''
    ) {
    }
}
