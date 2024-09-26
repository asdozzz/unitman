<?php

namespace App\Unitman\Infra\BackgroundJob\StatistikaPoProektu;

use App\BackgroundJob\Infra\Service\AbstractBackgroundJob;
use App\Unitman\Infra\Projection\StatistikaPoProektuProjection;
use App\Utils\EventSauce\ProjectionsManager;

final class StatistikaPoProektuBackgroundJob extends AbstractBackgroundJob
{
    public function __construct(private ProjectionsManager $projectionsManager)
    {
    }

    function getName(): string
    {
        return 'statistika_po_proektu';
    }

    function run(): bool
    {
        $this->projectionsManager->pullProjectionByName(StatistikaPoProektuProjection::PROJECTION_NAME);

        return true;
    }

    function getDelay(): int
    {
        return 10;
    }


}
