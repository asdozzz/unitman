<?php

namespace App\Unitman\Business\ReadModel\Unit\WebhookEvent\WebhookEventPayload;

readonly class WebhookEventPayloadAccount
{
    public function __construct(
        public string $id,
        public string $email,
    )
    {
    }

}
