<?php

namespace App\Unitman\Infra\BackgroundJob\WebhookEvent;

use App\BackgroundJob\Infra\Service\AbstractBackgroundJob;
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

    function run(): bool
    {
        $this->projectionsManager->pullProjectionByName(WebhookEventProjection::PROJECTION_NAME);

        return true;
    }
}
