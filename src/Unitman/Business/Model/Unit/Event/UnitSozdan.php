<?php

namespace App\Unitman\Business\Model\Unit\Event;

final class UnitSozdan
{
    public function __construct(
        public readonly string $id,
        public readonly string $projectId,
        public readonly string $name,
        public readonly string $branch
    )
    {
    }

}
