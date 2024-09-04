<?php

namespace App\Unitman\Business\Command\Project;

use App\Utils\Converter\JsonBodySerializableInterface;

final class DobavitPeremenuyuVProekt implements JsonBodySerializableInterface
{
    public function __construct(
        public readonly string $projectId,
        public readonly string $tip,
        public readonly string $code,
        public readonly string $value,
    ) { }
}
