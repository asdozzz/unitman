<?php

namespace App\Unitman\Business\ReadModel;

final class ProjectUsersList
{
    public function __construct(
        public readonly string $userId,
        public readonly string $role
    )
    {
    }

}
