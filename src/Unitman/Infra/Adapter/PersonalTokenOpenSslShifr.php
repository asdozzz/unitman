<?php

declare(strict_types=1);

namespace App\Unitman\Infra\Adapter;

use App\Secrets\Business\Exception\InvalidKeyMaterial;
use App\Secrets\Business\Exception\SecretNotFound;
use App\Secrets\Business\Model\KeyPurpose;
use App\Secrets\Business\Port\EncryptionKeyProvider;
use App\Secrets\Infra\Adapter\ArraySecretProvider;
use App\Secrets\Infra\Adapter\SecretEncryptionKeyProvider;
use App\Unitman\Business\Port\UmeetShifrovatSekrety;

/**
 * AES-256-GCM поверх ext-openssl для личных и репозиторных токенов.
 *
 * Формат строки: base64(nonce[12] || tag[16] || ciphertext).
 * AAD привязывает шифротекст к конкретной записи (account_id:token_id или repoId).
 *
 * Ключевой материал приходит из `EncryptionKeyProvider` (WS-9). Формат
 * шифротекста и AAD не изменились: данные WS-2 продолжают расшифровываться.
 */
final class PersonalTokenOpenSslShifr implements UmeetShifrovatSekrety
{
    private const CIPHER = 'aes-256-gcm';
    private const NONCE_LENGTH = 12;
    private const TAG_LENGTH = 16;

    private ?string $key = null;

    public function __construct(private readonly EncryptionKeyProvider $keys)
    {
    }

    /**
     * Фабрика для тестов и legacy-точек сборки: ключ уже в base64.
     *
     * @internal Не использовать в production-DI; там передаётся провайдер.
     */
    public static function fromBase64Key(string $base64Key): self
    {
        return new self(new SecretEncryptionKeyProvider(
            new ArraySecretProvider(['LICHNIY_TOKEN_CREDENTIALS_KEY' => $base64Key]),
        ));
    }

    public function zashifrovat(string $secret, string $aad): string
    {
        $key = $this->poluchitKlyuch();

        $nonce = random_bytes(self::NONCE_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $secret,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $aad,
            self::TAG_LENGTH,
        );

        if ($ciphertext === false) {
            throw new \DomainException('lichniy_token.encrypt_failed');
        }

        return base64_encode($nonce . $tag . $ciphertext);
    }

    public function rasshifrovat(string $cipher, string $aad): string
    {
        $key = $this->poluchitKlyuch();

        $raw = base64_decode($cipher, true);

        // Пустой плейнтекст даёт ровно nonce + tag: строго меньше — уже порча.
        if ($raw === false || \strlen($raw) < self::NONCE_LENGTH + self::TAG_LENGTH) {
            throw new \DomainException('lichniy_token.decrypt_failed');
        }

        $nonce = substr($raw, 0, self::NONCE_LENGTH);
        $tag = substr($raw, self::NONCE_LENGTH, self::TAG_LENGTH);
        $ciphertext = substr($raw, self::NONCE_LENGTH + self::TAG_LENGTH);

        $plain = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $aad,
        );

        if ($plain === false) {
            throw new \DomainException('lichniy_token.decrypt_failed');
        }

        return $plain;
    }

    /**
     * @throws \DomainException если ключ не задан или имеет неверный формат
     */
    private function poluchitKlyuch(): string
    {
        if ($this->key !== null) {
            return $this->key;
        }

        try {
            return $this->key = $this->keys->getEncryptionKey(KeyPurpose::REPO_TOKENS);
        } catch (SecretNotFound $e) {
            // Ключ не задан — фича выключена. Падаем громко при использовании,
            // а не при сборке контейнера; код ошибки сохранён для WS-2.
            throw new \DomainException('lichniy_token.key_not_configured', 0, $e);
        } catch (InvalidKeyMaterial $e) {
            throw new \DomainException('lichniy_token.invalid_key', 0, $e);
        }
    }
}
