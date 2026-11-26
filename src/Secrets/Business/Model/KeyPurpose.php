<?php

declare(strict_types=1);

namespace App\Secrets\Business\Model;

/**
 * Логические назначения ключей. Физические имена переменных задаются в
 * `SecretEncryptionKeyProvider`; один ключ может обслуживать несколько
 * назначений (репозиторные и личные токены используют один AES-ключ WS-2).
 */
final class KeyPurpose
{
    /** Токены репозиториев at rest (WS-2), AES-256-GCM, AAD = repoId. */
    public const REPO_TOKENS = 'repo_tokens';

    /** Личные токены хранилищ, AES-256-GCM, AAD = accountId:tokenId. */
    public const PERSONAL_TOKENS = 'personal_tokens';

    /** HMAC-ключ stateless upload-токенов Mediateka. */
    public const UPLOAD_TOKENS = 'upload_tokens';

    /** Корневой ключ детерминированных секретов раннеров (HMAC-SHA256). */
    public const RUNNER_SECRETS = 'runner_secrets';
}
