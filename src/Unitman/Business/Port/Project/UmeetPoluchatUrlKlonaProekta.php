<?php

declare(strict_types=1);

namespace App\Unitman\Business\Port\Project;

use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\Repo;

interface UmeetPoluchatUrlKlonaProekta
{
    /**
     * URL клонирования проекта вместе с токеном доступа.
     *
     * URL возвращается только Runner и не должен попадать в HTTP-ответы.
     */
    public function poluchitUrlKlonaProekta(Repo $repo, Project $project): string;
}
