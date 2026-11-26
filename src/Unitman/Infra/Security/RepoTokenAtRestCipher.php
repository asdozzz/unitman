<?php

declare(strict_types=1);

namespace App\Unitman\Infra\Security;

use App\Unitman\Business\Model\Repo\RepoTokenHint;

/**
 * AES-256-GCM для токенов репозиториев.
 *
 * Формат идентичен {@see \App\Unitman\Infra\Adapter\PersonalTokenOpenSslShifr}:
 * base64(nonce[12] || tag[16] || ciphertext). AAD привязывает шифротекст к
 * репозиторию (repoId).
 *
 * Класс нужен Doctrine-миграции, у которой нет DI-контейнера, но есть
 * переменная окружения с ключом. Консольная команда использует DI-адаптер.
 *
 * Ключ никогда не логируется и не пишется в БД.
 */
final class RepoTokenAtRestCipher
{
    private const CIPHER = 'aes-256-gcm';
    private const NONCE_LENGTH = 12;
    private const TAG_LENGTH = 16;
    private const KEY_LENGTH = 32;

    private function __construct(private readonly string $key)
    {
    }

    /**
     * @throws \DomainException если ключ не задан или имеет неверный формат
     */
    public static function fromBase64Key(string $rawKey): self
    {
        if ($rawKey === '') {
            throw new \DomainException('repo_token.key_not_configured');
        }

        $decoded = base64_decode($rawKey, true);

        if ($decoded === false || strlen($decoded) !== self::KEY_LENGTH) {
            throw new \DomainException('repo_token.invalid_key');
        }

        return new self($decoded);
    }

    public static function fromEnvironment(): self
    {
        $raw = getenv('LICHNIY_TOKEN_CREDENTIALS_KEY');

        if (!is_string($raw) || $raw === '') {
            throw new \DomainException('repo_token.key_not_configured');
        }

        return self::fromBase64Key($raw);
    }

    public function encrypt(string $plain, string $aad): string
    {
        $nonce = random_bytes(self::NONCE_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plain,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $aad,
            self::TAG_LENGTH,
        );

        if ($ciphertext === false) {
            throw new \DomainException('repo_token.encrypt_failed');
        }

        return base64_encode($nonce . $tag . $ciphertext);
    }

    public function decrypt(string $cipher, string $aad): string
    {
        $raw = base64_decode($cipher, true);

        if ($raw === false || strlen($raw) < self::NONCE_LENGTH + self::TAG_LENGTH) {
            throw new \DomainException('repo_token.decrypt_failed');
        }

        $nonce = substr($raw, 0, self::NONCE_LENGTH);
        $tag = substr($raw, self::NONCE_LENGTH, self::TAG_LENGTH);
        $ciphertext = substr($raw, self::NONCE_LENGTH + self::TAG_LENGTH);

        $plain = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $aad,
        );

        if ($plain === false) {
            throw new \DomainException('repo_token.decrypt_failed');
        }

        return $plain;
    }

    public function hint(string $plain): string
    {
        return RepoTokenHint::fromPlainText($plain);
    }
}
