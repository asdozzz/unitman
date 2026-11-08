<?php

declare(strict_types=1);

namespace App\Unitman\Business\Command\VolumeType;

use App\Utils\Converter\JsonBodySerializableInterface;

/**
 * Привязка проектного архива медиатеки к типу тома.
 */
final class PrikrepitFaylKTipuVoluma implements JsonBodySerializableInterface
{
    public function __construct(
        public readonly string $projectId = '',
        public readonly string $code = '',
        public readonly string $fileId = ''
    ) {
    }
}
