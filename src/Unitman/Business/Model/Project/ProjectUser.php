<?php

namespace App\Unitman\Business\Model\Project;

final class ProjectUser
{
    public function __construct(
        public readonly string $userId,
        public readonly ProjectUserRole $userRole
    )
    {
    }

}
