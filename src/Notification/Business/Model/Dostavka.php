<?php

declare(strict_types=1);

namespace App\Notification\Business\Model;

/**
 * Состояние доставки уведомления по конкретному каналу.
 */
final class Dostavka
{
    public function __construct(
        private readonly string $notificationId,
        private readonly string $channel,
        private readonly StatusDostavki $state,
        private readonly int $attempts,
        private readonly ?int $dispatchedAt,
        private readonly ?int $sentAt,
        private readonly ?string $lastError,
    ) {
    }

    public function getNotificationId(): string
    {
        return $this->notificationId;
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function getState(): StatusDostavki
    {
        return $this->state;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function getDispatchedAt(): ?int
    {
        return $this->dispatchedAt;
    }

    public function getSentAt(): ?int
    {
        return $this->sentAt;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }
}
