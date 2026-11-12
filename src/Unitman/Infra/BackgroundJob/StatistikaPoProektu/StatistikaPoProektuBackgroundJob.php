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
        // false пробрасываем наружу: заблокированная проекция — не успех.
        return $this->projectionsManager->pullProjectionByName(StatistikaPoProektuProjection::PROJECTION_NAME);
    }

    function getDelay(): int
    {
        return 10;
    }


}
