<?php

namespace App\Unitman\Business\Command;

final class AddRepo
{
    public function __construct(
        public readonly string $repoType,
        public readonly string $repoName,
        public readonly string $repoUrl,
        public readonly string $repoLogin,
        public readonly string $repoPassword
    )
    {
    }
}
