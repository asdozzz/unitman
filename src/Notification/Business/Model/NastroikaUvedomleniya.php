<?php

declare(strict_types=1);

namespace App\Notification\Business\Model;

/**
 * Персональная настройка типа уведомления.
 */
final class NastroikaUvedomleniya
{
    public function __construct(
        private readonly string $accountId,
        private readonly TipUvedomleniya $type,
        private readonly bool $enabled,
        private readonly int $updatedAt,
    ) {
    }

    public function getAccountId(): string
    {
        return $this->accountId;
    }

    public function getType(): TipUvedomleniya
    {
        return $this->type;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getUpdatedAt(): int
    {
        return $this->updatedAt;
    }
}
