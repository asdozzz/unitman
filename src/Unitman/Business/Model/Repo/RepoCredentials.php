<?php

namespace App\Unitman\Business\Model\Repo;

/**
 * Учётные данные репозитория.
 *
 * Открытый токен здесь не хранится: только шифротекст `tokenCipher`
 * (AES-256-GCM, AAD = идентификатор агрегата) и необратимый `tokenHint`.
 * Расшифровка выполняется исключительно по запросу через
 * {@see \App\Unitman\Business\Model\Repo::rasshifrovatToken()}.
 */
final class RepoCredentials
{
    public readonly string $url;

    private readonly string $tokenCipher;

    private readonly string $tokenHint;

    public function __construct(
        string $url,
        string $tokenCipher,
        string $tokenHint,
    )
    {
        if (empty($url)) {
            throw new \DomainException('repo.credentials.url_is_empty');
        }

        if ($tokenCipher === '') {
            throw new \DomainException('repo.credentials.token_is_empty');
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === FALSE) {
            throw new \DomainException('repo.credentials.url_invalid');
        }

        $this->url = $url;
        $this->tokenCipher = $tokenCipher;
        $this->tokenHint = $tokenHint;
    }

    public function getTokenCipher(): string
    {
        return $this->tokenCipher;
    }

    public function getTokenHint(): string
    {
        return $this->tokenHint;
    }
}
