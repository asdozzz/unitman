<?php

namespace App\Unitman\Infra\Temporal\Activity;

use App\Unitman\Infra\Projection\UnitRunnerJobsProjection;
use App\Utils\EventSauce\ProjectionsManager;
use Temporal\Activity\ActivityInterface;

#[ActivityInterface(prefix: 'UnitmanActivity.')]
final class UnitRunnerJobsProjectionActivity
{
    public function __construct(private ProjectionsManager $projectionsManager)
    {
    }

    public function handleNewEvents(): bool
    {
        $this->projectionsManager->pullProjectionByName(UnitRunnerJobsProjection::PROJECTION_NAME);

        return true;
    }
}
