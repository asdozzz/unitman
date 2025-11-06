<?php

namespace App\Unitman\Infra\BackgroundJob\WebhookEvent;

use App\BackgroundJob\Infra\Service\AbstractBackgroundJob;
use App\Unitman\Business\Model\WebhookEventJob;
use App\Unitman\Infra\Jobs\WebhookEventJobHandler;
use App\Unitman\Infra\Jobs\ZadachaDlyOcherediService;
use App\Unitman\Infra\Repository\Unit\SqlWebhookEventRepository;
use Doctrine\DBAL\Connection;

final class WebhookEventSender extends AbstractBackgroundJob
{
    public function __construct(
        private SqlWebhookEventRepository $webhookEventRepository,
        private ZadachaDlyOcherediService $zadachaDlyOcherediService
    )
    {
    }

    function getName(): string
    {
        return 'webhook_event_sender';
    }

    function run(): bool
    {
        $events = $this->webhookEventRepository->getEventsForSend();

        foreach ($events as $event) {
            try {
                $this->zadachaDlyOcherediService->dobavitZadachuVOchered(WebhookEventJobHandler::QUEUE_NAME, new WebhookEventJob($event->id), 2);
                $event->sent();
            } catch (\Throwable $e) {
                $event->setError($e->getMessage());
            }
            $this->webhookEventRepository->update($event);
        }

        return true;
    }
}
