<?php

namespace App\Unitman\Business\Model\Repo\Event;

final class RepoWasDeleted
{
    public function __construct(
        public readonly string $repoId,
    )
    {
    }

}
