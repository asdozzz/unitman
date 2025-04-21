<?php

namespace App\Unitman\Business\Command\Unit;

use App\Utils\Converter\JsonBodySerializableInterface;

final class PoluchitKonfigIzHranilisha implements JsonBodySerializableInterface
{
    public function __construct(
        public readonly string $projectId,
        public readonly string $branch
    )
    {
    }
}
