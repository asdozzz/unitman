<?php

namespace App\Unitman\Infra\BackgroundJob\ProjectRunnerJobs;

use App\BackgroundJob\Infra\Service\AbstractBackgroundJob;
use App\BackgroundJob\Infra\Service\BackgroundJobInterface;
use App\Unitman\Infra\Projection\ProjectRunnerJobsProjection;
use App\Unitman\Infra\Projection\UnitRunnerJobsProjection;
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
        $this->projectionsManager->pullProjectionByName(ProjectRunnerJobsProjection::PROJECTION_NAME);

        return true;
    }
}
