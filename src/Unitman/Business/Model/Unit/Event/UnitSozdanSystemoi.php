<?php

namespace App\Unitman\Business\Model\Unit\Event;

final class UnitSozdanSystemoi
{
    public function __construct(
        public readonly string $id,
        public readonly string $authorId,
        public readonly string $projectId,
        public readonly string $name,
        public readonly string $branch,
        public readonly array $stateAsArray
    )
    {
    }

}
