<?php

namespace App\Unitman\Business\Model\Project\Event;

final class ProjectWasNotDeleted
{
    public function __construct(
        public readonly string $id,
        public readonly string $errorText,
        public readonly bool $isActive
    )
    {
    }
}
