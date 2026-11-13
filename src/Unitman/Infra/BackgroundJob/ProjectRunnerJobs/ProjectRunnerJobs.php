<?php

declare(strict_types=1);

namespace App\Unitman\Infra\BackgroundJob\ProjectRunnerJobs;

use App\BackgroundJob\Infra\Service\AbstractBackgroundJob;
use App\Unitman\Infra\Projection\ProjectRunnerJobsProjection;
use App\Utils\EventSauce\ProjectionsManager;

final class ProjectRunnerJobs extends AbstractBackgroundJob
{
    public function __construct(private ProjectionsManager $projectionsManager)
    {
    }

    function getName(): string
    {
        return 'project_runner_jobs';
    }

    function run(): bool
    {
        // Занятая блокировка — не ошибка: проекцию двигает другой процесс.
        $this->projectionsManager->pullProjectionByName(ProjectRunnerJobsProjection::PROJECTION_NAME);

        return true;
    }
}
