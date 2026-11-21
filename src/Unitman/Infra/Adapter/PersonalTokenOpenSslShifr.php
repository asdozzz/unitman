<?php

declare(strict_types=1);

namespace App\Unitman\Infra\Adapter;

use App\Unitman\Business\Port\UmeetShifrovatSekrety;

/**
 * AES-256-GCM поверх ext-openssl для личных токенов хранилищ.
 *
 * Формат строки: base64(nonce[12] || tag[16] || ciphertext).
 * AAD привязывает шифротекст к конкретной записи (account_id:token_id).
 */
final class PersonalTokenOpenSslShifr implements UmeetShifrovatSekrety
{
    private const CIPHER = 'aes-256-gcm';
    private const NONCE_LENGTH = 12;
    private const TAG_LENGTH = 16;
    private const KEY_LENGTH = 32;

    /**
     * Сырое значение ключа из окружения. Разбирается лениво, чтобы отсутствие
     * ключа не ломало компиляцию контейнера и не связанные с фичей команды.
     */
    private string $rawKey;

    private ?string $key = null;

    public function __construct(string $lichniyTokenCredentialsKey)
    {
        $this->rawKey = $lichniyTokenCredentialsKey;
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

        if ($this->rawKey === '') {
            // Ключ не задан — фича личных токенов выключена. Падаем громко
            // именно при использовании, а не при сборке контейнера.
            throw new \DomainException('lichniy_token.key_not_configured');
        }

        $decoded = base64_decode($this->rawKey, true);

        if ($decoded === false || \strlen($decoded) !== self::KEY_LENGTH) {
            throw new \DomainException('lichniy_token.invalid_key');
        }

        return $this->key = $decoded;
    }
}
