<?php

declare(strict_types=1);

namespace App\Unitman\Business\Port\Project;

use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\Repo;

interface UmeetPoluchatUrlKlonaProekta
{
    /**
     * Чистый URL клонирования проекта — без userinfo/токена.
     *
     * Токен выдаётся отдельным короткоживущим грантом broker-эндпоинта.
     */
    public function poluchitUrlKlonaProekta(Repo $repo, Project $project): string;
}
