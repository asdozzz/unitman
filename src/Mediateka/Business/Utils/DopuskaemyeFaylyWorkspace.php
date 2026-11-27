<?php

declare(strict_types=1);

namespace App\Mediateka\Business\Utils;

use DomainException;
use Symfony\Component\String\UnicodeString;

/**
 * Правила файлов, которые можно прикреплять к workspace.
 *
 * Файлы читает агент, поэтому разрешены только изображения и простой текст.
 * Список расширений задаётся `MEDIATEKA_WORKSPACE_ALLOWED_EXT`, но
 * исполняемые и активные типы запрещены всегда, даже если их добавить в env.
 */
final class DopuskaemyeFaylyWorkspace
{
    public const NAME_MAX_LENGTH = 128;

    /** Расширения, которые нельзя разрешить конфигом. */
    private const ZAPRESHENNYE_EXT = [
        'sh', 'bash', 'zsh', 'fish', 'ksh', 'csh',
        'py', 'pyc', 'pyo', 'rb', 'pl', 'pm', 'php', 'phtml',
        'js', 'mjs', 'cjs', 'ts', 'tsx', 'jsx', 'vue', 'svelte',
        'java', 'class', 'jar', 'kt', 'kts', 'scala', 'groovy',
        'go', 'rs', 'c', 'h', 'cpp', 'cc', 'cxx', 'hpp', 'cs',
        'swift', 'm', 'mm', 'lua', 'r', 'julia',
        'exe', 'dll', 'so', 'dylib', 'bin', 'com', 'msi', 'deb', 'rpm',
        'bat', 'cmd', 'ps1', 'psm1', 'vbs', 'wsf', 'hta',
        'sql', 'html', 'htm', 'xhtml', 'svg', 'svgz', 'xml.hta',
        'pem', 'key', 'crt', 'cer', 'p12', 'pfx',
    ];

    /** Расширения, для которых проверяется сигнатура изображения. */
    private const IZOBRAZHENIYA = [
        'png' => IMAGETYPE_PNG,
        'jpg' => IMAGETYPE_JPEG,
        'jpeg' => IMAGETYPE_JPEG,
        'gif' => IMAGETYPE_GIF,
        'webp' => IMAGETYPE_WEBP,
    ];

    private const MIME_PO_RASSHIRENIYU = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'txt' => 'text/plain',
        'md' => 'text/markdown',
        'csv' => 'text/csv',
        'tsv' => 'text/tab-separated-values',
        'json' => 'application/json',
        'yaml' => 'application/yaml',
        'yml' => 'application/yaml',
        'xml' => 'application/xml',
        'log' => 'text/plain',
    ];

    /**
     * @var array<string, true>
     */
    private array $razreshennye;

    public function __construct(
        string $allowedExtensions,
        private readonly int $workspaceMaxFileSize,
        private readonly int $workspaceMaxFiles,
    ) {
        $razreshennye = [];

        foreach (explode(',', strtolower($allowedExtensions)) as $extension) {
            $extension = trim(ltrim($extension, '.'));

            if ($extension === '' || in_array($extension, self::ZAPRESHENNYE_EXT, true)) {
                continue;
            }

            if (isset(self::MIME_PO_RASSHIRENIYU[$extension])) {
                $razreshennye[$extension] = true;
            }
        }

        $this->razreshennye = $razreshennye;
    }

    /**
     * @return list<string>
     */
    public function razreshennyeRasshireniya(): array
    {
        return array_keys($this->razreshennye);
    }

    public function maxFileSize(): int
    {
        return $this->workspaceMaxFileSize;
    }

    public function maxFiles(): int
    {
        return $this->workspaceMaxFiles;
    }

    /**
     * Приводит имя к безопасному виду и проверяет расширение.
     */
    public function normalizovatImya(string $name): string
    {
        $name = str_replace("\0", '', trim($name));
        $name = str_replace('\\', '/', $name);
        $name = (string) basename($name);

        if ($name === '' || $name === '.' || $name === '..') {
            throw new DomainException('mediateka.workspace.name_is_empty');
        }

        if (!mb_check_encoding($name, 'UTF-8')) {
            throw new DomainException('mediateka.workspace.name_is_invalid');
        }

        $name = $this->vAscii($name);
        $name = strtolower($name);

        $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        $base = (string) pathinfo($name, PATHINFO_FILENAME);

        if ($extension === '') {
            throw new DomainException('mediateka.workspace.name_without_extension');
        }

        if (!isset($this->razreshennye[$extension]) || in_array($extension, self::ZAPRESHENNYE_EXT, true)) {
            throw new DomainException('mediateka.workspace.file_type_forbidden');
        }

        $base = (string) preg_replace('/[^a-z0-9._-]+/', '-', $base);
        $base = trim($base, '.-_');

        if ($base === '') {
            $base = 'file';
        }

        $name = $base . '.' . $extension;

        if (strlen($name) > self::NAME_MAX_LENGTH) {
            throw new DomainException('mediateka.workspace.name_is_too_long');
        }

        if ($this->zapreshennoeImya($name)) {
            throw new DomainException('mediateka.workspace.name_is_reserved');
        }

        return $name;
    }

    public function contentType(string $name): string
    {
        $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));

        return self::MIME_PO_RASSHIRENIYU[$extension] ?? 'application/octet-stream';
    }

    /**
     * Проверяет размер и содержимое уже загруженного файла.
     */
    public function proveritFayl(string $path, string $normalizedName, int $size): void
    {
        if ($size <= 0) {
            throw new DomainException('mediateka.workspace.file_is_empty');
        }

        if ($size > $this->workspaceMaxFileSize) {
            throw new DomainException('mediateka.workspace.file_is_too_large');
        }

        $extension = strtolower((string) pathinfo($normalizedName, PATHINFO_EXTENSION));

        if (isset(self::IZOBRAZHENIYA[$extension])) {
            $this->proveritIzobrazhenie($path, self::IZOBRAZHENIYA[$extension]);

            return;
        }

        $this->proveritTekst($path);
    }

    private function proveritIzobrazhenie(string $path, int $expectedType): void
    {
        $info = @getimagesize($path);

        if ($info === false || ($info[2] ?? null) !== $expectedType) {
            throw new DomainException('mediateka.workspace.file_signature_mismatch');
        }
    }

    private function proveritTekst(string $path): void
    {
        $content = @file_get_contents($path);

        if ($content === false) {
            throw new DomainException('mediateka.workspace.file_is_unreadable');
        }

        if (str_contains($content, "\0")) {
            throw new DomainException('mediateka.workspace.file_is_binary');
        }

        if (!mb_check_encoding($content, 'UTF-8')) {
            throw new DomainException('mediateka.workspace.file_is_not_utf8');
        }
    }

    private function zapreshennoeImya(string $name): bool
    {
        foreach (['workspace.code-workspace', 'unitman_files', '.git', '.unitman'] as $reserved) {
            if ($name === $reserved) {
                return true;
            }
        }

        return str_starts_with($name, '.');
    }

    private function vAscii(string $value): string
    {
        if (!mb_check_encoding($value, 'UTF-8')) {
            return (string) preg_replace('/[^\x20-\x7E]+/', ' ', $value);
        }

        try {
            return (new UnicodeString($value))->ascii()->toString();
        } catch (\Throwable) {
            return (string) preg_replace('/[^\x20-\x7E]+/', ' ', $value);
        }
    }
}
