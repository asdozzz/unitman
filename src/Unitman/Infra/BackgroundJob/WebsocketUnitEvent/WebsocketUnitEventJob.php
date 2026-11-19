<?php

namespace App\Unitman\Infra\BackgroundJob\WebsocketUnitEvent;

use App\BackgroundJob\Infra\Service\AbstractBackgroundJob;
use App\BackgroundJob\Infra\Service\BackgroundJobGroup;
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

    public function getGroup(): BackgroundJobGroup
    {
        return BackgroundJobGroup::projections;
    }

    function run(): bool
    {
        // Занятая блокировка — не ошибка: проекцию двигает другой процесс.
        $this->projectionsManager->pullProjectionByName(WebsocketUnitEventProjection::PROJECTION_NAME);

        return true;
    }
}
