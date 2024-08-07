<?php

namespace App\Unitman\Infra\BackgroundJob\UnitRunnerJobs;

use App\BackgroundJob\Infra\Service\AbstractBackgroundJob;
use App\BackgroundJob\Infra\Service\BackgroundJobInterface;
use App\Unitman\Infra\Projection\UnitRunnerJobsProjection;
use App\Utils\EventSauce\ProjectionsManager;

final class UnitRunnerJobs extends AbstractBackgroundJob
{
    public function __construct(private ProjectionsManager $projectionsManager)
    {
    }

    function getName(): string
    {
        return 'unit_runner_jobs';
    }

    function run(): bool
    {
        $this->projectionsManager->pullProjectionByName(UnitRunnerJobsProjection::PROJECTION_NAME);

        return true;
    }
}
