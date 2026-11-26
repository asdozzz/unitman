<?php

declare(strict_types=1);

namespace App\Secrets\Tests\Infra\Adapter;

use App\Secrets\Business\Exception\InvalidKeyMaterial;
use App\Secrets\Business\Model\KeyPurpose;
use App\Secrets\Infra\Adapter\ArraySecretProvider;
use App\Secrets\Infra\Adapter\SecretEncryptionKeyProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * WS-9: AES-256 key material is validated, never truncated or padded.
 *
 * Synthetic keys only: base64("test-only-personal-token-key-32b") = 32 bytes.
 */
final class SecretEncryptionKeyProviderTest extends TestCase
{
    private const VALID_AES_KEY = 'dGVzdC1vbmx5LXBlcnNvbmFsLXRva2VuLWtleS0zMmI=';
    private const INVALID_AES_KEY = 'dGVzdC1vbmx5LWtleS0xNg==';
    private const SYNTHETIC_HMAC_KEY = 'test-hmac-key-not-for-production';

    #[Test]
    public function returns_decoded_aes_256_key(): void
    {
        $provider = $this->provider([
            'LICHNIY_TOKEN_CREDENTIALS_KEY' => self::VALID_AES_KEY,
        ]);

        $key = $provider->getEncryptionKey(KeyPurpose::REPO_TOKENS);

        self::assertSame(32, strlen($key));
        self::assertSame(base64_decode(self::VALID_AES_KEY, true), $key);
    }

    #[Test]
    public function rejects_wrong_length_aes_key_without_leaking_value(): void
    {
        $provider = $this->provider([
            'LICHNIY_TOKEN_CREDENTIALS_KEY' => self::INVALID_AES_KEY,
        ]);

        try {
            $provider->getEncryptionKey(KeyPurpose::PERSONAL_TOKENS);
            self::fail('Wrong-length AES key must be rejected.');
        } catch (InvalidKeyMaterial $e) {
            self::assertStringContainsString('LICHNIY_TOKEN_CREDENTIALS_KEY', $e->getMessage());
            self::assertStringNotContainsString(self::INVALID_AES_KEY, $e->getMessage());
        }
    }

    #[Test]
    public function rejects_invalid_base64_aes_key(): void
    {
        $provider = $this->provider([
            'LICHNIY_TOKEN_CREDENTIALS_KEY' => '***not-base64***',
        ]);

        $this->expectException(InvalidKeyMaterial::class);
        $provider->getEncryptionKey(KeyPurpose::REPO_TOKENS);
    }

    #[Test]
    public function missing_hmac_key_throws_not_found(): void
    {
        $provider = $this->provider([]);

        $this->expectException(\App\Secrets\Business\Exception\SecretNotFound::class);
        $provider->getHmacKey(KeyPurpose::RUNNER_SECRETS);
    }

    #[Test]
    public function whitespace_only_hmac_key_is_invalid(): void
    {
        $provider = $this->provider([
            'RUNNER_SECRET_MASTER_KEY' => '   ',
        ]);

        $this->expectException(InvalidKeyMaterial::class);
        $provider->getHmacKey(KeyPurpose::RUNNER_SECRETS);
    }

    #[Test]
    public function returns_hmac_key_verbatim(): void
    {
        $provider = $this->provider([
            'RUNNER_SECRET_MASTER_KEY' => self::SYNTHETIC_HMAC_KEY,
        ]);

        self::assertSame(self::SYNTHETIC_HMAC_KEY, $provider->getHmacKey(KeyPurpose::RUNNER_SECRETS));
    }

    #[Test]
    public function required_names_are_unique_physical_variables(): void
    {
        $provider = $this->provider([]);

        $names = $provider->requiredSecretNames();

        self::assertSame([
            'LICHNIY_TOKEN_CREDENTIALS_KEY',
            'MEDIATEKA_UPLOAD_TOKEN_SECRET',
            'RUNNER_SECRET_MASTER_KEY',
        ], $names);
        self::assertSame($names, array_values(array_unique($names)));
    }

    private function provider(array $values): SecretEncryptionKeyProvider
    {
        return new SecretEncryptionKeyProvider(new ArraySecretProvider($values));
    }
}
