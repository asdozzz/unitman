<?php

declare(strict_types=1);

namespace App\Unitman\Business\Command\VolumeType;

use App\Utils\Converter\JsonBodySerializableInterface;

/**
 * Отвязка архива от типа тома.
 *
 * `udalitFayl = true` удаляет архив из медиатеки проекта целиком,
 * иначе файл остаётся в медиатеке без связи с типом.
 */
final class OtkrepitFaylOtTipaVoluma implements JsonBodySerializableInterface
{
    public function __construct(
        public readonly string $projectId = '',
        public readonly string $code = '',
        public readonly string $fileId = '',
        public readonly bool $udalitFayl = false
    ) {
    }
}
