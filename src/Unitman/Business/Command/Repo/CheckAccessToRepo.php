<?php

namespace App\Unitman\Business\Command\Repo;


final class CheckAccessToRepo
{
    public function __construct(
        public readonly string $repoId
    )
    {
    }

}
