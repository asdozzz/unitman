<?php

namespace App\Unitman\Business\Command\Repo;

/**
 * Изменение учётных данных репозитория.
 *
 * `$token` опционален (write-only контракт WS-5):
 *  - null / пустая строка — сохранить текущий секрет без изменений;
 *  - непустая строка — зашифровать и заменить секрет.
 *
 * `$repoUrl` опционален — если не задан, сохраняется текущий URL.
 */
final class ChangeCredentialsOfRepo
{
    public function __construct(
        public readonly string $repoId,
        public readonly ?string $token = null,
        public readonly ?string $repoUrl = null
    )
    {
    }

}
