<?php

declare(strict_types=1);

namespace App\Secrets\Tests\Infra\Adapter;

use App\Secrets\Business\Exception\InsecureSecretFile;
use App\Secrets\Business\Exception\InvalidSecretName;
use App\Secrets\Business\Exception\SecretNotFound;
use App\Secrets\Business\Exception\UnsupportedSecretOperation;
use App\Secrets\Infra\Adapter\FileSecretProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * WS-9: file backend enforces 0600 and never allows traversal.
 *
 * All values are synthetic `test-...-not-for-production` strings.
 */
final class FileSecretProviderTest extends TestCase
{
    private const SECRET_NAME = 'RUNNER_SECRET_MASTER_KEY';
    private const SYNTHETIC_VALUE = 'test-file-secret-not-for-production';

    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/unitman-secrets-test-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            @chmod($file, 0600);
            @unlink($file);
        }
        @rmdir($this->directory);
    }

    #[Test]
    public function reads_owner_only_file(): void
    {
        $this->writeSecret(self::SECRET_NAME, self::SYNTHETIC_VALUE, 0600);

        $provider = new FileSecretProvider($this->directory);

        self::assertTrue($provider->has(self::SECRET_NAME));
        self::assertSame(self::SYNTHETIC_VALUE, $provider->get(self::SECRET_NAME));
    }

    #[Test]
    public function rejects_world_readable_file(): void
    {
        $this->writeSecret(self::SECRET_NAME, self::SYNTHETIC_VALUE, 0644);

        $provider = new FileSecretProvider($this->directory);

        $this->expectException(InsecureSecretFile::class);
        $this->expectExceptionMessage(self::SECRET_NAME);
        $provider->get(self::SECRET_NAME);
    }

    #[Test]
    public function rejects_group_readable_file(): void
    {
        $this->writeSecret(self::SECRET_NAME, self::SYNTHETIC_VALUE, 0640);

        $provider = new FileSecretProvider($this->directory);

        $this->expectException(InsecureSecretFile::class);
        $provider->get(self::SECRET_NAME);
    }

    #[Test]
    public function rejects_directory_traversal_names(): void
    {
        $provider = new FileSecretProvider($this->directory);

        $this->expectException(InvalidSecretName::class);
        $provider->get('../escape');
    }

    #[Test]
    public function missing_file_throws_named_error(): void
    {
        $provider = new FileSecretProvider($this->directory);

        $this->expectException(SecretNotFound::class);
        $this->expectExceptionMessage(self::SECRET_NAME);
        $provider->get(self::SECRET_NAME);
    }

    #[Test]
    public function file_backend_cannot_rotate(): void
    {
        $provider = new FileSecretProvider($this->directory);

        $this->expectException(UnsupportedSecretOperation::class);
        $provider->rotate(self::SECRET_NAME);
    }

    private function writeSecret(string $name, string $value, int $mode): void
    {
        $path = $this->directory . '/' . $name;
        file_put_contents($path, $value);
        chmod($path, $mode);
    }
}
