<?php

namespace App\Unitman\Business\Model\Project\Event;

final class ProjectDataWasChanged
{
    public function __construct(
        public readonly string $id,
        public readonly string $newName,
        public readonly string $newProxyHost,
        public readonly int $memoryLimit = 3072
    ) {}
}
