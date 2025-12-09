<?php

namespace App\Unitman\Business\ReadModel\Unit;

readonly class WebhookEventDlySpiska
{
    public function __construct(
        public string $id,
        public string $type,
        public string $payload,
        public ?string $response,
        public int $unixtime,
        public bool $sent
    )
    {
    }

}
