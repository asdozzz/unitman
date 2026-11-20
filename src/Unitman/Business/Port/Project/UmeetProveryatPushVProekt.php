<?php

declare(strict_types=1);

namespace App\Unitman\Business\Port\Project;

use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\Repo;

interface UmeetProveryatPushVProekt
{
    /**
     * Умеет ли пользователь пушить в проект хостинга.
     */
    public function canPush(Repo $repo, Project $project): bool;
}
