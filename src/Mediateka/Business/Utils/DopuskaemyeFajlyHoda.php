<?php

declare(strict_types=1);

namespace App\Mediateka\Business\Utils;

use DomainException;

/**
 * Правила файлов, которые создаёт агент в ходе OpenCode.
 *
 * Слой отвечает только за общую безопасность хранения: детерминированное
 * имя, текстовое содержимое, безопасный MIME и общий лимит размера файла.
 * Политика артефактов (количество, отдельные лимиты) — вне этого слоя.
 */
final class DopuskaemyeFajlyHoda
{
    public const NAME_MAX_LENGTH = 128;

    private const MIME_PO_RASSHIRENIYU = [
        'md' => 'text/markdown',
        'txt' => 'text/plain',
        'json' => 'application/json',
        'yaml' => 'application/yaml',
        'yml' => 'application/yaml',
        'csv' => 'text/csv',
        'log' => 'text/plain',
    ];

    private const PROSMOTR_RASSHIRENIYA = [
        'md' => true,
        'txt' => true,
        'json' => true,
        'yaml' => true,
        'yml' => true,
        'csv' => true,
        'log' => true,
    ];

    public function __construct(private readonly int $maxFileSize)
    {
    }

    /**
     * Приводит имя к безопасному виду и проверяет расширение.
     *
     * База имени сохраняет регистр, расширение приводится к нижнему регистру:
     * так результат нормализации детерминирован и совпадает с хранимым именем
     * в уникальном индексе `(opencode_turn_id, name)`.
     */
    public function normalizovatImya(string $name): string
    {
        $name = str_replace(['\\', "\0", "\r", "\n"], ['/', '', '', ''], $name);
        $name = (string) basename($name);
        $name = trim($name);

        if ($name === '' || $name === '.' || $name === '..' || str_starts_with($name, '.')) {
            throw new DomainException('mediateka.file.name_is_invalid');
        }

        if (!mb_check_encoding($name, 'UTF-8')) {
            throw new DomainException('mediateka.file.name_is_invalid');
        }

        $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        $base = (string) pathinfo($name, PATHINFO_FILENAME);

        if ($extension === '' || $base === '') {
            throw new DomainException('mediateka.file.name_is_invalid');
        }

        $name = $base . '.' . $extension;

        if (strlen($name) > self::NAME_MAX_LENGTH) {
            throw new DomainException('mediateka.file.name_is_invalid');
        }

        if (!isset(self::MIME_PO_RASSHIRENIYU[$extension])) {
            throw new DomainException('mediateka.file.type_is_forbidden');
        }

        return $name;
    }

    /**
     * Проверяет, что содержимое — непустой текст в UTF-8 без NUL-байтов.
     */
    public function proveritSoderzhimoe(string $content): void
    {
        if ($content === '') {
            throw new DomainException('mediateka.workspace.file_is_empty');
        }

        if (str_contains($content, "\0") || !mb_check_encoding($content, 'UTF-8')) {
            throw new DomainException('mediateka.file.content_is_invalid');
        }
    }

    public function contentType(string $name): string
    {
        $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));

        if (!isset(self::MIME_PO_RASSHIRENIYU[$extension])) {
            throw new DomainException('mediateka.file.type_is_forbidden');
        }

        return self::MIME_PO_RASSHIRENIYU[$extension];
    }

    public function maxFileSize(): int
    {
        return $this->maxFileSize;
    }

    public function podderzhivaetProsmotr(string $name): bool
    {
        $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));

        return isset(self::PROSMOTR_RASSHIRENIYA[$extension]);
    }
}
