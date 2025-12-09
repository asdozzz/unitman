<?php

namespace App\Unitman\Business\Model\Unit\Event;

final class UnitSozdan
{
    public function __construct(
        public readonly string $id,
        public readonly string $authorId,
        public readonly string $projectId,
        public readonly string $name,
        public readonly string $branch,
        public readonly array $stateAsArray,
        public readonly array $values = [],
        public readonly int $memoryLimit = 3072
    )
    {
    }

}
