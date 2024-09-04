<?php

namespace App\Unitman\Business\Command\Project;

use App\Utils\Converter\JsonBodySerializableInterface;

final class IzmenitZnacheniePeremenoiProekta implements JsonBodySerializableInterface
{
    public function __construct(
        public readonly string $projectId,
        public readonly string $code,
        public readonly string $newValue,
    ) { }
}
