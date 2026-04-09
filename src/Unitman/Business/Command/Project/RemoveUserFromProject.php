<?php

namespace App\Unitman\Business\Command\Project;

final class RemoveUserFromProject
{
    public function __construct(
        public readonly string $id,
        public readonly string $userId
    )
    {
    }
}
