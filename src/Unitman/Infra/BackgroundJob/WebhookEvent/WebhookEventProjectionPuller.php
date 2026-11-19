<?php

namespace App\Unitman\Infra\BackgroundJob\WebhookEvent;

use App\BackgroundJob\Infra\Service\AbstractBackgroundJob;
use App\BackgroundJob\Infra\Service\BackgroundJobGroup;
use App\Unitman\Infra\Projection\WebhookEventProjection;
use App\Utils\EventSauce\ProjectionsManager;

final class WebhookEventProjectionPuller extends AbstractBackgroundJob
{
    public function __construct(private ProjectionsManager $projectionsManager)
    {
    }
    function getName(): string
    {
        return 'webhook_event_puller';
    }

    public function getGroup(): BackgroundJobGroup
    {
        return BackgroundJobGroup::projections;
    }

    function run(): bool
    {
        // Занятая блокировка — не ошибка: проекцию двигает другой процесс.
        $this->projectionsManager->pullProjectionByName(WebhookEventProjection::PROJECTION_NAME);

        return true;
    }
}
