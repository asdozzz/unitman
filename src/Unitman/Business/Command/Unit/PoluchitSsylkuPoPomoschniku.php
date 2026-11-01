<?php

namespace App\Unitman\Business\Command\Unit;

use App\Utils\Converter\JsonBodySerializableInterface;

final class PoluchitSsylkuPoPomoschniku implements JsonBodySerializableInterface
{
    public function __construct(
        public readonly string $projectId,
        public readonly string $service,
        public readonly int $port,
        public readonly string $targetUnitId,
        public readonly ?string $suffix = null
    )
    {
    }
}
