<?php

declare(strict_types=1);

namespace App\Unitman\Business\Port\Project;

use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\Repo;

/**
 * Проверка push-прав конкретного (личного) токена хранилища.
 */
interface UmeetProveryatPushTokenom
{
    public function canPushTokenom(string $token, Repo $repo, Project $project): bool;
}
