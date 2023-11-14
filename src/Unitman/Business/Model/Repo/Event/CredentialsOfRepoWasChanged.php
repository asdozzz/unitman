<?php

namespace App\Unitman\Business\Model\Repo\Event;

final class CredentialsOfRepoWasChanged
{
    public function __construct(
        public readonly string $repoId,
        public readonly string $repoUrl,
        public readonly string $token
    )
    {
    }
}
