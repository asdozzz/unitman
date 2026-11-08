<?php

declare(strict_types=1);

namespace App\Mediateka\Business\Command;

final class ZagruzitFaylUnita
{
    /**
     * Владелец архива ровно один: `unitId` — файл юнита, `projectId` — архив
     * каталога типов томов. Оба пустые — черновик. Одновременно задавать нельзя.
     *
     * @param resource $stream поток с содержимым архива
     */
    public function __construct(
        public readonly string $unitId,
        public readonly string $name,
        public readonly int $size,
        public readonly ?int $contentLength,
        public readonly ?string $uploadedBy,
        public readonly mixed $stream,
        public readonly string $projectId = ''
    ) {
    }
}
