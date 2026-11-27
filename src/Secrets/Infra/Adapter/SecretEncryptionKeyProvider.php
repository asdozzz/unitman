<?php

declare(strict_types=1);

namespace App\Secrets\Infra\Adapter;

use App\Secrets\Business\Exception\InvalidKeyMaterial;
use App\Secrets\Business\Model\KeyPurpose;
use App\Secrets\Business\Port\EncryptionKeyProvider;
use App\Secrets\Business\Port\SecretProvider;

/**
 * Ключевой материал из абстракции `SecretProvider`.
 *
 * Физические имена и типы:
 * - OPENCODE_CREDENTIALS_KEY — AES-256-GCM (base64 32 байта), реквизиты OpenCode;
 * - LICHNIY_TOKEN_CREDENTIALS_KEY — AES-256-GCM (base64 32 байта), обслуживает
 *   repo_tokens (WS-2) и personal_tokens; формат и AAD не меняются;
 * - MEDIATEKA_UPLOAD_TOKEN_SECRET — HMAC-ключ upload-токенов;
 * - RUNNER_SECRET_MASTER_KEY — HMAC-SHA256 корень секретов раннеров.
 *
 * Валидация строгая: base64 без паддинга/усечения, ровно 32 байта для AES.
 * Сообщения содержат только имена переменных.
 */
final class SecretEncryptionKeyProvider implements EncryptionKeyProvider
{
    private const AES_256_KEY_BYTES = 32;

    /** @var array<string, string> */
    private const PURPOSE_TO_NAME = [
        KeyPurpose::OPENCODE_CREDENTIALS => 'OPENCODE_CREDENTIALS_KEY',
        KeyPurpose::REPO_TOKENS => 'LICHNIY_TOKEN_CREDENTIALS_KEY',
        KeyPurpose::PERSONAL_TOKENS => 'LICHNIY_TOKEN_CREDENTIALS_KEY',
        KeyPurpose::UPLOAD_TOKENS => 'MEDIATEKA_UPLOAD_TOKEN_SECRET',
        // Тот же физический ключ, что и runner_secrets; в requiredSecretNames
        // попадает один раз благодаря array_unique.
        KeyPurpose::UNIT_VARIABLES => 'RUNNER_SECRET_MASTER_KEY',
        KeyPurpose::RUNNER_SECRETS => 'RUNNER_SECRET_MASTER_KEY',
    ];

    /** @var array<string, true> */
    private const AES_256_NAMES = [
        'OPENCODE_CREDENTIALS_KEY' => true,
        'LICHNIY_TOKEN_CREDENTIALS_KEY' => true,
    ];

    public function __construct(private readonly SecretProvider $secrets)
    {
    }

    public function getEncryptionKey(string $purpose): string
    {
        $name = $this->nameForPurpose($purpose);

        return $this->decodeAes256Key($name, $this->secrets->get($name));
    }

    public function getHmacKey(string $purpose): string
    {
        $name = $this->nameForPurpose($purpose);
        $raw = $this->secrets->get($name);

        if (trim($raw) === '') {
            throw new InvalidKeyMaterial(sprintf('Secret "%s" must be a non-empty HMAC key', $name));
        }

        return $raw;
    }

    public function requiredSecretNames(): array
    {
        return array_values(array_unique(array_values(self::PURPOSE_TO_NAME)));
    }

    public function validate(string $secretName): void
    {
        if (!in_array($secretName, $this->requiredSecretNames(), true)) {
            throw new \InvalidArgumentException(sprintf('Unknown required secret "%s"', $secretName));
        }

        $raw = $this->secrets->get($secretName);

        if (isset(self::AES_256_NAMES[$secretName])) {
            $this->decodeAes256Key($secretName, $raw);

            return;
        }

        if (trim($raw) === '') {
            throw new InvalidKeyMaterial(sprintf('Secret "%s" must be a non-empty HMAC key', $secretName));
        }
    }

    private function nameForPurpose(string $purpose): string
    {
        if (!isset(self::PURPOSE_TO_NAME[$purpose])) {
            throw new \InvalidArgumentException(sprintf('Unknown key purpose "%s"', $purpose));
        }

        return self::PURPOSE_TO_NAME[$purpose];
    }

    private function decodeAes256Key(string $name, string $raw): string
    {
        $decoded = base64_decode($raw, true);

        if ($decoded === false || strlen($decoded) !== self::AES_256_KEY_BYTES) {
            throw new InvalidKeyMaterial(sprintf(
                'Secret "%s" must be base64 of exactly %d bytes (AES-256); no truncation or padding is applied',
                $name,
                self::AES_256_KEY_BYTES,
            ));
        }

        return $decoded;
    }
}
