<?php

namespace App\Unitman\Infra\Service;

use App\Unitman\Infra\Projection\SpisokUnitovProjection;
use App\Utils\EventSauce\ProjectionsManager;

final class RebuildService
{
    public function __construct(private ProjectionsManager $projectionsManager)
    {
    }

    function handle(string $projectionName, string $id): void
    {
        $this->projectionsManager->rebuildById($projectionName, $id);
    }
}
