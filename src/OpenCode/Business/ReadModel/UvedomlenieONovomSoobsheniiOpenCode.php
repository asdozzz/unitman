<?php

declare(strict_types=1);

namespace App\OpenCode\Business\ReadModel;

/**
 * Уведомление о новом сообщении OpenCode.
 *
 * Форма `toArray()` — публичный контракт Centrifugo и HTTP API (раздел 4
 * плана): владелец уведомления (`accountId`) в payload не попадает, он нужен
 * только инфраструктуре для выбора персонального канала и фильтрации в БД.
 */
final class UvedomlenieONovomSoobsheniiOpenCode
{
    public const EVENT_TYPE = 'new_message';

    public function __construct(
        public readonly string $id,
        public readonly string $accountId,
        public readonly string $sessionId,
        public readonly string $turnId,
        public readonly string $sessionTitle,
        public readonly string $status,
        public readonly ?string $kind,
        public readonly int $createdAt,
        public readonly string $sessionType = 'unit',
    ) {
    }

    /**
     * @return array<string, string|int|null>
     */
    public function toArray(): array
    {
        return [
            'eventType' => self::EVENT_TYPE,
            'id' => $this->id,
            'sessionId' => $this->sessionId,
            'sessionType' => $this->sessionType,
            'turnId' => $this->turnId,
            'sessionTitle' => $this->sessionTitle,
            'status' => $this->status,
            'kind' => $this->kind,
            'createdAt' => $this->createdAt,
        ];
    }
}
