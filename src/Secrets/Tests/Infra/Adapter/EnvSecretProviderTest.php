<?php

declare(strict_types=1);

namespace App\Secrets\Tests\Infra\Adapter;

use App\Secrets\Business\Exception\SecretNotFound;
use App\Secrets\Business\Exception\UnsupportedSecretOperation;
use App\Secrets\Infra\Adapter\EnvSecretProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * WS-9: env backend is fail-closed.
 *
 * Synthetic values only, clearly marked `test-...-not-for-production`.
 */
final class EnvSecretProviderTest extends TestCase
{
    private const SECRET_NAME = 'RUNNER_SECRET_MASTER_KEY';
    private const SYNTHETIC_VALUE = 'test-env-secret-not-for-production';

    #[Test]
    public function missing_secret_throws_exception_naming_variable_only(): void
    {
        $provider = new EnvSecretProvider([]);

        try {
            $provider->get(self::SECRET_NAME);
            self::fail('EnvSecretProvider must fail closed for a missing variable.');
        } catch (SecretNotFound $e) {
            self::assertStringContainsString(self::SECRET_NAME, $e->getMessage());
            self::assertStringNotContainsString(self::SYNTHETIC_VALUE, $e->getMessage());
            self::assertStringNotContainsString('value=', $e->getMessage());
        }
    }

    #[Test]
    public function empty_value_is_treated_as_absent(): void
    {
        $provider = new EnvSecretProvider([self::SECRET_NAME => '']);

        self::assertFalse($provider->has(self::SECRET_NAME));
        $this->expectException(SecretNotFound::class);
        $provider->get(self::SECRET_NAME);
    }

    #[Test]
    public function present_value_is_returned_verbatim(): void
    {
        $provider = new EnvSecretProvider([self::SECRET_NAME => self::SYNTHETIC_VALUE]);

        self::assertTrue($provider->has(self::SECRET_NAME));
        self::assertSame(self::SYNTHETIC_VALUE, $provider->get(self::SECRET_NAME));
    }

    #[Test]
    public function env_backend_cannot_rotate(): void
    {
        $provider = new EnvSecretProvider([]);

        $this->expectException(UnsupportedSecretOperation::class);
        $provider->rotate(self::SECRET_NAME);
    }
}
