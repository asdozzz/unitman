<?php

declare(strict_types=1);

namespace App\Unitman\Infra\BackgroundJob\UnitRunnerJobs;

use App\BackgroundJob\Infra\Service\AbstractBackgroundJob;
use App\Unitman\Infra\Projection\ProzesUnitaProjection;
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
        // false пробрасываем наружу: заблокированная проекция — не успех.
        return $this->projectionsManager->pullProjectionByName(ProzesUnitaProjection::PROJECTION_NAME);
    }
}
