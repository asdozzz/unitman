<?php

declare(strict_types=1);

namespace App\Secrets\Infra\Adapter;

use App\Secrets\Business\Exception\InsecureSecretFile;
use App\Secrets\Business\Exception\SecretNotFound;
use App\Secrets\Business\Exception\SecretProviderNotConfigured;
use App\Secrets\Business\Exception\SecretProviderUnavailable;
use App\Secrets\Business\Exception\UnsupportedSecretOperation;
use App\Secrets\Business\Model\SecretName;
use App\Secrets\Business\Port\SecretProvider;

/**
 * Файловый бэкенд: `SECRETS_DIR/<NAME>`.
 *
 * - имя валидируется как `^[A-Z0-9_]+$` (никакого traversal);
 * - файл обязан быть 0600: группа/остальные не должны иметь доступ;
 * - значение не логируется и не попадает в исключения.
 */
final class FileSecretProvider implements SecretProvider
{
    private readonly string $directory;

    public function __construct(string $directory)
    {
        $this->directory = rtrim(trim($directory), '/');

        if ($this->directory === '') {
            throw SecretProviderNotConfigured::forBackend('file', 'SECRETS_DIR is empty');
        }
    }

    public function get(string $name): string
    {
        $secretName = SecretName::fromString($name);
        $path = $this->directory . '/' . $secretName->value();

        if (!is_file($path) || !is_readable($path)) {
            throw SecretNotFound::named($secretName->value(), 'file');
        }

        $this->assertOwnerOnlyPermissions($path, $secretName->value());

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw SecretProviderUnavailable::forBackend('file', 'read_failed');
        }

        // Допускается только завершающий перевод строки (артефакт редактора).
        $value = rtrim($contents, "\r\n");

        if ($value === '') {
            throw SecretNotFound::named($secretName->value(), 'file');
        }

        return $value;
    }

    public function has(string $name): bool
    {
        try {
            $this->get($name);

            return true;
        } catch (SecretNotFound) {
            return false;
        }
    }

    public function rotate(string $name): void
    {
        throw new UnsupportedSecretOperation(
            'file: rotation is an operator action (write new file with mode 0600, then restart)',
        );
    }

    private function assertOwnerOnlyPermissions(string $path, string $name): void
    {
        $permissions = @fileperms($path);

        if ($permissions === false) {
            throw SecretProviderUnavailable::forBackend('file', 'stat_failed');
        }

        // 0077 — любые права группы/остальных (read/write/execute).
        if (($permissions & 0077) !== 0) {
            throw InsecureSecretFile::forFile($name);
        }
    }
}
