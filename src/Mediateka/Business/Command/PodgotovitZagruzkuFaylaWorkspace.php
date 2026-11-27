<?php

declare(strict_types=1);

namespace App\Mediateka\Business\Command;

/**
 * Подготовка прямой загрузки файла workspace в MinIO.
 *
 * Файл сохраняется как черновик; привязка к workspace выполняется OpenCode
 * после проверки владельца.
 */
final class PodgotovitZagruzkuFaylaWorkspace
{
    public function __construct(
        public readonly string $name,
        public readonly int $size,
        public readonly ?string $contentType,
        public readonly ?string $uploadedBy,
    ) {
    }
}
