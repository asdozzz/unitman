<?php

namespace App\Unitman\Business\ReadModel\Unit\WebhookEvent\WebhookEventPayload;

readonly class WebhookEventPayloadProject
{
    public function __construct(
        public string $id,
        public string $name,
    )
    {
    }
}
