<?php

namespace App\Unitman\Business\Port\Unit\WebhookEvent;

use App\Unitman\Business\ReadModel\Unit\WebhookEvent;

interface WebhookEventRepository
{
    function getById(string $id): WebhookEvent;

    /**
     * @return WebhookEvent[]
     * */
    function getEventsForSend(int $limit = 100): array;
    /**
     * @return WebhookEvent[]
     * */
    function findAllByWebhookId(string $webhookId, int $limit = 10, int $offset = 0): array;

    function getCountByWebhookId(string $webhookId): int;
 }
