<?php

namespace App\Unitman\Business\Model\Repo\Event;

/**
 * Событие изменения учётных данных репозитория.
 *
 * Содержит только шифротекст секрета (`tokenCipher`, AAD = repoId) и
 * необратимый `tokenHint`. Открытого `token` в событии нет; legacy-события
 * поднимаются на границе десериализации без переписывания payload в БД.
 */
final class CredentialsOfRepoWasChanged
{
    public function __construct(
        public readonly string $repoId,
        public readonly string $repoUrl,
        public readonly string $tokenCipher = '',
        public readonly string $tokenHint = '',
    )
    {
    }
}
