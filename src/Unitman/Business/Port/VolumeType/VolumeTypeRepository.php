<?php

declare(strict_types=1);

namespace App\Unitman\Business\Port\VolumeType;

use App\Unitman\Business\Model\VolumeType\VolumeType;

interface VolumeTypeRepository
{
    public function save(VolumeType $type): void;

    public function getByProjectAndCode(string $projectId, string $code): VolumeType;

    public function findByProjectAndCode(string $projectId, string $code): ?VolumeType;

    /**
     * @return VolumeType[]
     */
    public function najtiPoProektu(string $projectId): array;

    public function udalit(string $typeId): void;

    public function esliEstFayly(string $typeId): bool;

    public function udalitPoProektu(string $projectId): void;
}
