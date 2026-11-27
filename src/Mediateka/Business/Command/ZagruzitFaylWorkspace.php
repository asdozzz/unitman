<?php

declare(strict_types=1);

namespace App\Mediateka\Business\Command;

/**
 * Загрузка одного файла workspace как черновика.
 *
 * Файл ещё не привязан к workspace: привязка выполняется отдельным запросом
 * OpenCode после проверки владельца workspace.
 */
final class ZagruzitFaylWorkspace
{
    /**
     * @param resource $stream поток с содержимым файла
     */
    public function __construct(
        public readonly string $name,
        public readonly int $size,
        public readonly ?int $contentLength,
        public readonly ?string $uploadedBy,
        public readonly mixed $stream,
    ) {
    }
}
