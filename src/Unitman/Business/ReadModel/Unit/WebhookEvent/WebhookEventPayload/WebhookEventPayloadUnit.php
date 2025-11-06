<?php

namespace App\Unitman\Business\ReadModel\Unit\WebhookEvent\WebhookEventPayload;

final class WebhookEventPayloadUnit
{
    public function __construct(
        public string $id,
        public string $name,
        public string $branch,
    )
    {
    }
}
