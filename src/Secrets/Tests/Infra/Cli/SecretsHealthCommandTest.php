<?php

declare(strict_types=1);

namespace App\Secrets\Tests\Infra\Cli;

use App\Secrets\Infra\Adapter\ArraySecretProvider;
use App\Secrets\Infra\Adapter\SecretEncryptionKeyProvider;
use App\Secrets\Infra\Cli\SecretsHealthCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * WS-9: `app:secrets:health` exits non-zero and prints names only.
 *
 * Synthetic keys: the AES value is base64("test-only-personal-token-key-32b").
 */
final class SecretsHealthCommandTest extends TestCase
{
    private const AES_KEY = 'dGVzdC1vbmx5LXBlcnNvbmFsLXRva2VuLWtleS0zMmI=';
    private const UPLOAD_KEY = 'test-upload-hmac-key-not-for-production';
    private const RUNNER_KEY = 'test-runner-master-key-not-for-production';

    #[Test]
    public function all_required_secrets_present_exits_zero(): void
    {
        $tester = $this->tester([
            'OPENCODE_CREDENTIALS_KEY' => self::AES_KEY,
            'LICHNIY_TOKEN_CREDENTIALS_KEY' => self::AES_KEY,
            'MEDIATEKA_UPLOAD_TOKEN_SECRET' => self::UPLOAD_KEY,
            'RUNNER_SECRET_MASTER_KEY' => self::RUNNER_KEY,
        ]);

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('secrets health: OK', $tester->getDisplay());
        self::assertStringNotContainsString(self::AES_KEY, $tester->getDisplay());
        self::assertStringNotContainsString(self::RUNNER_KEY, $tester->getDisplay());
    }

    #[Test]
    public function missing_runner_key_exits_non_zero_and_reports_name_only(): void
    {
        $tester = $this->tester([
            'OPENCODE_CREDENTIALS_KEY' => self::AES_KEY,
            'LICHNIY_TOKEN_CREDENTIALS_KEY' => self::AES_KEY,
            'MEDIATEKA_UPLOAD_TOKEN_SECRET' => self::UPLOAD_KEY,
        ]);

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('MISSING: RUNNER_SECRET_MASTER_KEY', $tester->getDisplay());
        self::assertStringNotContainsString(self::RUNNER_KEY, $tester->getDisplay());
    }

    #[Test]
    public function invalid_aes_key_reports_invalid_without_value(): void
    {
        $tester = $this->tester([
            'OPENCODE_CREDENTIALS_KEY' => self::AES_KEY,
            'LICHNIY_TOKEN_CREDENTIALS_KEY' => 'dGVzdC1vbmx5LWtleS0xNg==',
            'MEDIATEKA_UPLOAD_TOKEN_SECRET' => self::UPLOAD_KEY,
            'RUNNER_SECRET_MASTER_KEY' => self::RUNNER_KEY,
        ]);

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('INVALID: LICHNIY_TOKEN_CREDENTIALS_KEY', $tester->getDisplay());
        self::assertStringNotContainsString('dGVzdC1vbmx5LWtleS0xNg==', $tester->getDisplay());
    }

    private function tester(array $values): CommandTester
    {
        $secrets = new ArraySecretProvider($values);

        return new CommandTester(new SecretsHealthCommand(new SecretEncryptionKeyProvider($secrets)));
    }
}
