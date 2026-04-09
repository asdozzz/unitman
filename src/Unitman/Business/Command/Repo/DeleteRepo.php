<?php

namespace App\Unitman\Business\Command\Repo;

final class DeleteRepo
{
    public function __construct(
        public readonly string $repoId
    )
    {
    }

}
