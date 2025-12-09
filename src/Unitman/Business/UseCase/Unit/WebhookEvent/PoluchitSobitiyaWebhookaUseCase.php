<?php

namespace App\Unitman\Business\UseCase\Unit\WebhookEvent;

use App\Unitman\Business\Command\Project\Webhook\PoluchitSobitiyaWebhooka;
use App\Unitman\Business\Port\Unit\WebhookEvent\WebhookEventRepository;
use App\Unitman\Business\ReadModel\Unit\WebhookEvent;
use App\Unitman\Business\ReadModel\Unit\WebhookEventDlySpiska;

final class PoluchitSobitiyaWebhookaUseCase
{
    public function __construct(private WebhookEventRepository $repository)
    {
    }

    function handle(PoluchitSobitiyaWebhooka $query): array
    {
        $webhookEvents = $this->repository->findAllByWebhookId($query->webhookId, $query->limit, $query->offset);
        return array(
            'list' => array_map(function(WebhookEvent $webhookEvent): WebhookEventDlySpiska {
                $webhookEventResponse = $webhookEvent->getResponse();
                return new WebhookEventDlySpiska(
                    $webhookEvent->id,
                    $webhookEvent->payload->type->value,
                    json_encode($webhookEvent->payload, JSON_PRETTY_PRINT),
                    !empty($webhookEventResponse) ? json_encode($webhookEventResponse, JSON_PRETTY_PRINT) : $webhookEvent->getError(),
                    $webhookEvent->payload->unixtime,
                    (bool) $webhookEvent->getSentInQueue()
                );
            },$webhookEvents),
            'totalRecords' => $this->repository->getCountByWebhookId($query->webhookId),
        );
    }
}
