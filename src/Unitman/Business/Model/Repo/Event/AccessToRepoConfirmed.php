<?php

namespace App\Unitman\Business\Model\Repo\Event;

final class AccessToRepoConfirmed
{
    public function __construct(
        public readonly string $repoId
    )
    {
    }

}
