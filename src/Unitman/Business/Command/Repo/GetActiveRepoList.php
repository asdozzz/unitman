<?php

namespace App\Unitman\Business\Command\Repo;

final class GetActiveRepoList
{
    public function __construct(public readonly int $limit = 10, public readonly int $offset = 0)
    {
    }

}
