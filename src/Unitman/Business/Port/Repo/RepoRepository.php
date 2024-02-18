<?php

namespace App\Unitman\Business\Port\Repo;

use App\Unitman\Business\Model\Repo;

interface RepoRepository
{
    public function getById(string $repoId): Repo;

    public function save(Repo $repo): void;
}
