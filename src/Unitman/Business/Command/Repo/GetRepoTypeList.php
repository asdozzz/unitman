<?php

namespace App\Unitman\Business\Command\Repo;

final class GetRepoTypeList
{
    public function __construct(public readonly int $limit = 100, public readonly int $offset = 0)
    {
    }
}
