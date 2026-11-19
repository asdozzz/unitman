<?php

declare(strict_types=1);

namespace App\Mediateka\Business\Command;

/**
 * Подготовка прямой загрузки zip-архива в MinIO.
 *
 * Владелец файла ровно один: `unitId` — файл юнита, `projectId` — архив
 * каталога типов томов. Оба пустые — черновик. Одновременно задавать нельзя.
 */
final class PodgotovitZagruzkuFayla
{
    public function __construct(
        public readonly string $unitId,
        public readonly string $projectId,
        public readonly string $name,
        public readonly int $size,
        public readonly string $contentType,
        public readonly ?string $uploadedBy
    ) {
    }
}
