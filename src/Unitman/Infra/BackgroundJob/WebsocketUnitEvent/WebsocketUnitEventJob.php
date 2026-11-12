<?php

namespace App\Unitman\Infra\BackgroundJob\WebsocketUnitEvent;

use App\BackgroundJob\Infra\Service\AbstractBackgroundJob;
use App\Unitman\Infra\Projection\WebsocketUnitEventProjection;
use App\Utils\EventSauce\ProjectionsManager;

final class WebsocketUnitEventJob extends AbstractBackgroundJob
{
    public function __construct(private ProjectionsManager $projectionsManager)
    {
    }
    function getName(): string
    {
        return 'websocket_unit_event';
    }

    function run(): bool
    {
        // false пробрасываем наружу: заблокированная проекция — не успех.
        return $this->projectionsManager->pullProjectionByName(WebsocketUnitEventProjection::PROJECTION_NAME);
    }
}
