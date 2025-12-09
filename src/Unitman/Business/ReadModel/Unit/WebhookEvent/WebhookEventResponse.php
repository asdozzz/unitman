<?php

namespace App\Unitman\Business\ReadModel\Unit\WebhookEvent;

final class WebhookEventResponse
{
    public function __construct(
        public readonly int $code,
        public readonly string $response
    )
    {
    }

}
