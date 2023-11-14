<?php

namespace App\Unitman\Business\Model\Project\Event;

final class UserAddedToProject
{
    public function __construct(
        public readonly string $projectId,
        public readonly string $userId,
        public readonly string $role,
    ) {}
}
