<?php

declare(strict_types=1);

namespace App\Notification\Business\Model;

/**
 * Подписка браузера на Web Push.
 */
final class PushPodpiska
{
    public function __construct(
        private readonly string $id,
        private readonly string $accountId,
        private readonly string $endpoint,
        private readonly string $p256dh,
        private readonly string $auth,
        private readonly string $locale,
        private readonly int $createdAt,
        private readonly int $updatedAt,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getAccountId(): string
    {
        return $this->accountId;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function getP256dh(): string
    {
        return $this->p256dh;
    }

    public function getAuth(): string
    {
        return $this->auth;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): int
    {
        return $this->updatedAt;
    }
}
