<?php

declare(strict_types=1);

namespace App\Notification\Business\Model;

/**
 * Уведомление — общая запись для всех получателей.
 *
 * Персональный статус прочтения хранится отдельно в `notification_recipient`.
 * Текст не хранится: фронтенд и push-составитель локализуют `type` + `payload`.
 */
final class Uvedomlenie
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private readonly string $id,
        private readonly TipUvedomleniya $type,
        private readonly UrovenVazhnosti $severity,
        private readonly bool $requiresRead,
        private readonly array $payload,
        private readonly ?string $dedupKey,
        private readonly int $createdAt,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function sozdat(
        string $id,
        TipUvedomleniya $type,
        UrovenVazhnosti $severity,
        array $payload,
        ?string $dedupKey,
        int $createdAt,
    ): self {
        return new self(
            $id,
            $type,
            $severity,
            $severity->trebueProchteniya(),
            $payload,
            $dedupKey,
            $createdAt,
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getType(): TipUvedomleniya
    {
        return $this->type;
    }

    public function getSeverity(): UrovenVazhnosti
    {
        return $this->severity;
    }

    public function requiresRead(): bool
    {
        return $this->requiresRead;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getDedupKey(): ?string
    {
        return $this->dedupKey;
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }
}
