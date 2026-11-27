<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Utils;

/**
 * Имя и тип артефакта хода.
 *
 * Агентский файл — недоверенный ввод: имя приходит из query раннера, поэтому
 * нормализация обязана исключить traversal, скрытые файлы, активные типы и
 * превышение длины. Проверка и нормализация выполняются **до** регистрации:
 * именно нормализованное имя сохраняется и попадает в подпись/ссылку.
 */
final class ImyaFajlaHoda
{
    private const PREVIEW_EXT = ['md', 'txt', 'json', 'yaml', 'yml', 'csv', 'log'];

    /**
     * Жёсткий denylist активных/исполняемых типов. Он не пересекается с
     * allowlist из `OPENCODE_ARTIFACT_ALLOWED_EXT`, но остаётся на случай
     * расширения allowlist в конфигурации.
     */
    private const FORBIDDEN_EXT = [
        'html', 'htm', 'xhtml', 'svg', 'js', 'mjs', 'ts', 'php', 'sh', 'py',
        'exe', 'bin', 'so', 'dll', 'bat', 'cmd', 'ps1', 'sql',
    ];

    /**
     * @var list<string>
     */
    private readonly array $allowedExtensions;

    public function __construct(
        string $allowedExtensions,
        private readonly int $maxNameLength = 128,
    ) {
        $this->allowedExtensions = self::razobratRasshireniya($allowedExtensions);
    }

    /**
     * @throws \DomainException `opencode.artifact.name_is_invalid`,
     *                          `opencode.artifact.file_type_forbidden`
     */
    public function normalizovat(string $name): string
    {
        // Один разделитель: Windows-путь и posix-путь дают один basename.
        $name = str_replace('\\', '/', $name);

        // Управляющие символы, ломающие заголовки, логи и имена файлов.
        $name = str_replace(["\0", "\r", "\n"], '', $name);

        $name = basename($name);

        if ($name === '' || $name === '.' || $name === '..') {
            throw new \DomainException('opencode.artifact.name_is_invalid');
        }

        if (str_starts_with($name, '.')) {
            throw new \DomainException('opencode.artifact.name_is_invalid');
        }

        if (mb_strlen($name, 'UTF-8') > $this->maxNameLength) {
            throw new \DomainException('opencode.artifact.name_is_invalid');
        }

        $extension = self::rasshirenie($name);

        if ($extension === ''
            || in_array($extension, self::FORBIDDEN_EXT, true)
            || !in_array($extension, $this->allowedExtensions, true)
        ) {
            throw new \DomainException('opencode.artifact.file_type_forbidden');
        }

        // Расширение нормализуется в нижний регистр, базовая часть сохраняется:
        // одна и та же строка используется и при сохранении, и при проверке.
        $dot = strrpos($name, '.');

        if ($dot === false) {
            throw new \DomainException('opencode.artifact.file_type_forbidden');
        }

        return substr($name, 0, $dot + 1) . $extension;
    }

    public function contentType(string $name): string
    {
        return match (self::rasshirenie($name)) {
            'md' => 'text/markdown',
            'txt', 'log' => 'text/plain',
            'json' => 'application/json',
            'yaml', 'yml' => 'application/yaml',
            'csv' => 'text/csv',
            default => 'application/octet-stream',
        };
    }

    public function podderzhivaetProsmotr(string $name): bool
    {
        return in_array(self::rasshirenie($name), self::PREVIEW_EXT, true);
    }

    private static function rasshirenie(string $name): string
    {
        return strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
    }

    /**
     * @return list<string>
     */
    private static function razobratRasshireniya(string $allowedExtensions): array
    {
        $result = [];

        foreach (explode(',', $allowedExtensions) as $extension) {
            $extension = strtolower(trim($extension));

            if ($extension !== '') {
                $result[] = $extension;
            }
        }

        return array_values(array_unique($result));
    }
}
