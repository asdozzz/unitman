<?php

namespace App\Unitman\Business\Command\Repo;

final class ChangeCredentialsOfRepo
{
    public function __construct(
        public readonly string $repoId,
        public readonly string $token,
        public readonly ?string $repoUrl = null
    )
    {
    }

}
