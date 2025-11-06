<?php

namespace App\Unitman\Business\ReadModel\Unit;

use App\Unitman\Business\ReadModel\Unit\WebhookEvent\WebhookEventPayload;
use App\Unitman\Business\ReadModel\Unit\WebhookEvent\WebhookEventResponse;

final class WebhookEvent
{
    public function __construct(
        public readonly string $id,
        public readonly string $webhookId,
        public readonly WebhookEventPayload $payload,
        private int $sentInQueue = 0,
        private ?WebhookEventResponse $response = null,
        private ?string $error = null
    )
    {
    }

    function sent(): void
    {
        $this->sentInQueue = 1;
    }

    function setError(string $error): void
    {
        $this->error = $error;
    }

    function setResponse(WebhookEventResponse $response): void
    {
        $this->response = $response;
    }

    public function getResponse(): ?WebhookEventResponse
    {
        return $this->response;
    }

    public function getSentInQueue(): int
    {
        return $this->sentInQueue;
    }

    public function getError(): ?string
    {
        return $this->error;
    }
}
