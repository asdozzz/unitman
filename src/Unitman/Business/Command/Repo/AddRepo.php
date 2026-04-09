<?php

namespace App\Unitman\Business\Command\Repo;

final class AddRepo
{
    public function __construct(
        public readonly string $repoType,
        public readonly string $repoName,
        public readonly string $token,
        public readonly ?string $repoUrl,
    )
    {
    }
}
