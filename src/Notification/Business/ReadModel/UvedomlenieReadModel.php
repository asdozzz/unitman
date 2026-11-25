<?php

declare(strict_types=1);

namespace App\Notification\Business\ReadModel;

use App\Notification\Business\Model\TipUvedomleniya;
use App\Notification\Business\Model\UrovenVazhnosti;

/**
 * Уведомление в контексте текущего получателя.
 */
final class UvedomlenieReadModel
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly string $id,
        public readonly TipUvedomleniya $type,
        public readonly UrovenVazhnosti $severity,
        public readonly bool $requiresRead,
        public readonly array $payload,
        public readonly int $createdAt,
        public readonly ?int $readAt,
    ) {
    }

    public function isRead(): bool
    {
        return $this->readAt !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'severity' => $this->severity->value,
            'requiresRead' => $this->requiresRead,
            'payload' => $this->payload,
            'createdAt' => $this->createdAt,
            'read' => $this->requiresRead ? $this->isRead() : false,
        ];
    }
}
