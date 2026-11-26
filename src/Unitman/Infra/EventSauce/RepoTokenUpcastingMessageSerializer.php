<?php

declare(strict_types=1);

namespace App\Unitman\Infra\EventSauce;

use App\Unitman\Business\Model\Repo\RepoTokenHint;
use App\Unitman\Business\Port\UmeetShifrovatSekrety;
use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\Serialization\MessageSerializer;

/**
 * Upcaster для legacy-событий репозитория.
 *
 * До WS-2 события `RepoWasAdded` / `CredentialsOfRepoWasChanged` содержали
 * открытое поле `token`. Новый формат хранит `token_cipher` + `token_hint`
 * (snake_case — так ObjectHydrator пишет свойства). При чтении старого
 * сообщения мы:
 *  - шифруем открытый токен с AAD = repo_id;
 *  - подставляем `token_cipher` / `token_hint` в массив сообщения;
 *  - удаляем `token` только из массива в памяти.
 *
 * Сохранённый payload в event store НЕ переписывается. Санкционированная
 * перезапись старых строк — отдельная opt-in команда
 * `app:security:encrypt-repo-tokens --apply` (см. EncryptRepoTokensCommand).
 *
 * Декоратор стоит на общем `unitman.event_serializer`, поэтому одинаково
 * применяется и к реконституции агрегата, и к проекциям.
 */
final class RepoTokenUpcastingMessageSerializer implements MessageSerializer
{
    /** Типы событий, которые могли хранить открытый `token`. */
    private const REPO_EVENT_TYPES = [
        'RepoWasAdded',
        'CredentialsOfRepoWasChanged',
    ];

    public function __construct(
        private readonly MessageSerializer $inner,
        private readonly UmeetShifrovatSekrety $shifr,
    ) {
    }

    public function serializeMessage(Message $message): array
    {
        return $this->inner->serializeMessage($message);
    }

    public function unserializePayload(array $payload): Message
    {
        $eventType = $payload['headers']['__event_type'] ?? null;

        if (is_string($eventType) && in_array($eventType, self::REPO_EVENT_TYPES, true)) {
            $payload = $this->upcast($payload);
        }

        return $this->inner->unserializePayload($payload);
    }

    /**
     * @param array<string, mixed> $message
     * @return array<string, mixed>
     */
    private function upcast(array $message): array
    {
        $event = $message['payload'] ?? null;

        if (!is_array($event)) {
            return $message;
        }

        $cipher = $event['token_cipher'] ?? null;
        $plain = $event['token'] ?? null;

        // Новый формат либо нечего поднимать — оставляем как есть.
        if ((is_string($cipher) && $cipher !== '') || !is_string($plain) || $plain === '') {
            return $message;
        }

        $repoId = $event['repo_id'] ?? null;

        if (!is_string($repoId) || $repoId === '') {
            // Не можем привязать шифротекст к агрегату — падаем громко и без
            // открытого значения в тексте ошибки.
            throw new \DomainException('repo.token_upcast_missing_repo_id');
        }

        $message['payload']['token_cipher'] = $this->shifr->zashifrovat($plain, $repoId);
        $message['payload']['token_hint'] = RepoTokenHint::fromPlainText($plain);
        unset($message['payload']['token']);

        return $message;
    }
}
