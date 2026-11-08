<?php

declare(strict_types=1);

namespace App\Unitman\Business\Port\VolumeType;

use App\Unitman\Business\Model\VolumeType\VolumeTypeFile;

interface VolumeTypeFileRepository
{
    /**
     * Сохраняет связь; повторная вставка той же пары игнорируется.
     */
    public function save(VolumeTypeFile $link): void;

    public function esliSvyazEst(string $typeId, string $fileId): bool;

    public function udalitSvyaz(string $typeId, string $fileId): void;

    /**
     * @return VolumeTypeFile[]
     */
    public function najtiPoTipu(string $typeId): array;

    /**
     * Связи для набора файлов проекта: `fileId => [code, ...]`.
     *
     * Используется при проверке `fileId` в `unitman_volumes`.
     *
     * @param string[] $fileIds
     *
     * @return array<string, string[]>
     */
    public function najtiKodyTipovPoFaylam(string $projectId, array $fileIds): array;

    public function esliFaylPrivyazanKHOTyaByOdnomuTipu(string $fileId): bool;

    public function udalitPoProektu(string $projectId): void;
}
