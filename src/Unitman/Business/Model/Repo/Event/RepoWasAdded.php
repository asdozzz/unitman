<?php

namespace App\Unitman\Business\Model\Repo\Event;

/**
 * Событие добавления репозитория.
 *
 * Секрет хранится только в зашифрованном виде: `tokenCipher` (AES-256-GCM,
 * AAD = repoId) и необратимый `tokenHint`. Открытого `token` в событии нет.
 * Legacy-формат не поддерживается: БД создаётся с нуля по актуальным
 * миграциям, upcaster отсутствует.
 */
final class RepoWasAdded
{
    public function __construct(
        public readonly string $repoId,
        public readonly string $repoType,
        public readonly string $repoName,
        public readonly string $repoUrl,
        public readonly string $tokenCipher = '',
        public readonly string $tokenHint = '',
    )
    {
    }

}
