<?php

namespace App\Unitman\Infra\BackgroundJob\WebsocketProjectEvent;

use App\BackgroundJob\Infra\Service\AbstractBackgroundJob;
use App\Unitman\Infra\Projection\WebsocketProjectEventProjection;
use App\Utils\EventSauce\ProjectionsManager;

final class WebsocketProjectEventJob extends AbstractBackgroundJob
{
    public function __construct(private ProjectionsManager $projectionsManager)
    {
    }

    function getName(): string
    {
        return 'websocket_project_event';
    }

    function run(): bool
    {
        // Занятая блокировка — не ошибка: проекцию двигает другой процесс.
        $this->projectionsManager->pullProjectionByName(WebsocketProjectEventProjection::PROJECTION_NAME);

        return true;
    }
}
