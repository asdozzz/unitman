<?php

declare(strict_types=1);

namespace App\Account\Infra\Adapter;

use App\Account\Business\Port\UmeetShifrovatSekrety;

/**
 * AES-256-GCM поверх ext-openssl.
 *
 * Формат строки: base64(nonce[12] || tag[16] || ciphertext).
 * AAD привязывает шифротекст к конкретной записи (account_id:credential_id).
 */
final class OpenSslShifr implements UmeetShifrovatSekrety
{
    private const CIPHER = 'aes-256-gcm';
    private const NONCE_LENGTH = 12;
    private const TAG_LENGTH = 16;
    private const KEY_LENGTH = 32;

    private string $key;

    public function __construct(string $openCodeCredentialsKey)
    {
        if ($openCodeCredentialsKey === '') {
            // Ключ не задан — фича реквизитов выключена. Падаем громко и рано,
            // чтобы не сохранить в БД незашифрованный секрет.
            throw new \DomainException('account.opencode_credential.key_not_configured');
        }

        $decoded = base64_decode($openCodeCredentialsKey, true);

        if ($decoded === false || \strlen($decoded) !== self::KEY_LENGTH) {
            throw new \DomainException('account.opencode_credential.invalid_key');
        }

        $this->key = $decoded;
    }

    public function zashifrovat(string $otkrytyiTekst, string $aad): string
    {
        $nonce = random_bytes(self::NONCE_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $otkrytyiTekst,
            self::CIPHER,
            $this->key,
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
            $this->key,
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
}
