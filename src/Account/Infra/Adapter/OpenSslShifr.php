<?php

declare(strict_types=1);

namespace App\Account\Infra\Adapter;

use App\Account\Business\Port\UmeetShifrovatSekrety;
use App\Secrets\Business\Exception\InvalidKeyMaterial;
use App\Secrets\Business\Exception\SecretNotFound;
use App\Secrets\Business\Model\KeyPurpose;
use App\Secrets\Business\Port\EncryptionKeyProvider;
use App\Secrets\Infra\Adapter\ArraySecretProvider;
use App\Secrets\Infra\Adapter\SecretEncryptionKeyProvider;

/**
 * AES-256-GCM поверх ext-openssl.
 *
 * Формат строки: base64(nonce[12] || tag[16] || ciphertext).
 * AAD привязывает шифротекст к конкретной записи (account_id:credential_id).
 *
 * Ключевой материал приходит из `EncryptionKeyProvider` (WS-9); физический
 * секрет — `OPENCODE_CREDENTIALS_KEY` (base64 32 байта). Формат шифротекста
 * и AAD не изменились.
 */
final class OpenSslShifr implements UmeetShifrovatSekrety
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
     * Валидирует ключ сразу, чтобы секрет не мог уехать в БД незашифрованным.
     *
     * @internal Не использовать в production-DI; там передаётся провайдер.
     */
    public static function fromBase64Key(string $base64Key): self
    {
        $shifr = new self(new SecretEncryptionKeyProvider(
            new ArraySecretProvider(['OPENCODE_CREDENTIALS_KEY' => $base64Key]),
        ));

        $shifr->poluchitKlyuch();

        return $shifr;
    }

    public function zashifrovat(string $otkrytyiTekst, string $aad): string
    {
        $key = $this->poluchitKlyuch();

        $nonce = random_bytes(self::NONCE_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $otkrytyiTekst,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $aad,
            self::TAG_LENGTH
        );

        if ($ciphertext === false) {
            throw new \DomainException('account.opencode_credential.encrypt_failed');
        }

        return base64_encode($nonce . $tag . $ciphertext);
    }

    public function rasshifrovat(string $shifrotekst, string $aad): string
    {
        $key = $this->poluchitKlyuch();

        $raw = base64_decode($shifrotekst, true);

        // Строго меньше: пустой плейнтекст даёт полезную нагрузку ровно
        // nonce + tag, и такой шифротекст структурно корректен.
        if ($raw === false || \strlen($raw) < self::NONCE_LENGTH + self::TAG_LENGTH) {
            throw new \DomainException('account.opencode_credential.decrypt_failed');
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
            $aad
        );

        if ($plain === false) {
            throw new \DomainException('account.opencode_credential.decrypt_failed');
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
            return $this->key = $this->keys->getEncryptionKey(KeyPurpose::OPENCODE_CREDENTIALS);
        } catch (SecretNotFound $e) {
            // Ключ не задан — фича выключена. Падаем громко при использовании,
            // а не при сборке контейнера; код ошибки сохранён.
            throw new \DomainException('account.opencode_credential.key_not_configured', 0, $e);
        } catch (InvalidKeyMaterial $e) {
            throw new \DomainException('account.opencode_credential.invalid_key', 0, $e);
        }
    }
}
