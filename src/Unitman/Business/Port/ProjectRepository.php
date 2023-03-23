<?php

namespace App\Unitman\Business\Port;

use App\Unitman\Business\Model\Project;

interface ProjectRepository
{
    public function getById(string $id): Project;

    public function save(Project $project): void;
}
