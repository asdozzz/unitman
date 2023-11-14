<?php

namespace App\Unitman\Business\Command\Unit;

use App\Utils\Converter\JsonBodySerializableInterface;

final class SozdatUnit implements JsonBodySerializableInterface
{
    public function __construct(
        public readonly string $projectId,
        public readonly string $unitName,
        public readonly string $branch
    )
    {
    }
}
