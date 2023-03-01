<?php

namespace App\Unitman\Business\Model\Repo\Event;

final class RepoWasAdded
{
    public function __construct(
        public readonly string $repoId,
        public readonly string $repoType,
        public readonly string $repoName,
        public readonly string $repoUrl,
        public readonly string $repoLogin,
        public readonly string $repoPassword
    )
    {
    }

}
