<?php

namespace App\Unitman\Business\Command\Project;

final class AddUserToProject
{
    public function __construct(
        public readonly string $id,
        public readonly string $userId
    )
    {
    }

}
