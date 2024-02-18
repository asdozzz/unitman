<?php

namespace App\Unitman\Business\Port\Repo;

use App\Unitman\Business\Model\Repo;
use App\Unitman\Business\Model\RepoAdapter\CheckAccessResponse;

interface CanCheckAccessToRepo
{
    public function checkAccess(Repo $repo): CheckAccessResponse;
}
