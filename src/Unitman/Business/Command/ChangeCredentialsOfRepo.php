<?php

namespace App\Unitman\Business\Command;

final class ChangeCredentialsOfRepo
{
    public function __construct(
        public readonly string $repoId,
        public readonly string $repoUrl,
        public readonly string $repoLogin,
        public readonly string $repoPassword
    )
    {
    }

}
