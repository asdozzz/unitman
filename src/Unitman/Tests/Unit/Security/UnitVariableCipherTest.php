<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Unit\Security;

use App\Secrets\Infra\Adapter\ArraySecretProvider;
use App\Secrets\Infra\Adapter\SecretEncryptionKeyProvider;
use App\Unitman\Infra\Security\UnitVariableCipher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Hidden-переменные: AES-256-GCM per runner, AAD = unitId:variableId.
 *
 * Синтетический master key только для тестов.
 */
final class UnitVariableCipherTest extends TestCase
{
    private const MASTER_KEY = 'test-runner-master-key-not-for-production';
    private const RUNNER_ID = 'runner-1';
    private const UNIT_ID = 'unit-1';
    private const VARIABLE_ID = 'PV_SECRET';

    #[Test]
    public function encrypts_and_decrypts_with_runner_and_aad(): void
    {
        $cipher = $this->cipher();
        $encrypted = $cipher->encrypt('s3cr3t-value', self::UNIT_ID, self::VARIABLE_ID, self::RUNNER_ID);

        self::assertStringNotContainsString('s3cr3t-value', $encrypted);
        self::assertSame(
            's3cr3t-value',
            $cipher->decrypt($encrypted, self::UNIT_ID, self::VARIABLE_ID, self::RUNNER_ID),
        );
    }

    #[Test]
    public function another_runner_cannot_decrypt(): void
    {
        $cipher = $this->cipher();
        $encrypted = $cipher->encrypt('s3cr3t-value', self::UNIT_ID, self::VARIABLE_ID, self::RUNNER_ID);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.variables.decrypt_failed');

        $cipher->decrypt($encrypted, self::UNIT_ID, self::VARIABLE_ID, 'runner-2');
    }

    #[Test]
    public function another_variable_aad_cannot_decrypt(): void
    {
        $cipher = $this->cipher();
        $encrypted = $cipher->encrypt('s3cr3t-value', self::UNIT_ID, self::VARIABLE_ID, self::RUNNER_ID);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.variables.decrypt_failed');

        $cipher->decrypt($encrypted, self::UNIT_ID, 'PV_OTHER', self::RUNNER_ID);
    }

    #[Test]
    public function missing_master_key_is_fail_closed(): void
    {
        $cipher = new UnitVariableCipher(
            new SecretEncryptionKeyProvider(new ArraySecretProvider([])),
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.variables.master_key_not_configured');

        $cipher->encrypt('s3cr3t-value', self::UNIT_ID, self::VARIABLE_ID, self::RUNNER_ID);
    }

    private function cipher(): UnitVariableCipher
    {
        return new UnitVariableCipher(
            new SecretEncryptionKeyProvider(new ArraySecretProvider([
                'RUNNER_SECRET_MASTER_KEY' => self::MASTER_KEY,
            ])),
        );
    }
}
