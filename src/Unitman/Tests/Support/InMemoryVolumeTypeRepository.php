<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Support;

use App\Unitman\Business\Model\VolumeType\VolumeType;
use App\Unitman\Business\Port\VolumeType\VolumeTypeRepository;
use DomainException;

/**
 * Каталог типов томов в памяти — замена SQL-репозитория в тестах бизнес-слоя.
 */
final class InMemoryVolumeTypeRepository implements VolumeTypeRepository
{
    public function __construct(private readonly InMemoryKatalogStorage $storage)
    {
    }

    public function save(VolumeType $type): void
    {
        foreach ($this->storage->types as $existing) {
            if ($existing->projectId === $type->projectId && $existing->code === $type->code) {
                throw new DomainException('unit.volume_type.code_is_not_unique');
            }
        }

        $this->storage->types[$type->id] = $type;
    }

    public function getByProjectAndCode(string $projectId, string $code): VolumeType
    {
        $type = $this->findByProjectAndCode($projectId, $code);
        if ($type === null) {
            throw new DomainException('unit.volume_type.not_found');
        }

        return $type;
    }

    public function findByProjectAndCode(string $projectId, string $code): ?VolumeType
    {
        foreach ($this->storage->types as $type) {
            if ($type->projectId === $projectId && $type->code === trim($code)) {
                return $type;
            }
        }

        return null;
    }

    /**
     * @return VolumeType[]
     */
    public function najtiPoProektu(string $projectId): array
    {
        $result = array_values(array_filter(
            $this->storage->types,
            static fn (VolumeType $type): bool => $type->projectId === $projectId
        ));

        usort($result, static fn (VolumeType $a, VolumeType $b): int => strcmp($a->code, $b->code));

        return $result;
    }

    public function udalit(string $typeId): void
    {
        unset($this->storage->types[$typeId]);
    }

    public function esliEstFayly(string $typeId): bool
    {
        foreach ($this->storage->links as $link) {
            if ($link->typeId === $typeId) {
                return true;
            }
        }

        return false;
    }

    public function udalitPoProektu(string $projectId): void
    {
        foreach ($this->storage->types as $id => $type) {
            if ($type->projectId === $projectId) {
                unset($this->storage->types[$id]);
            }
        }
    }
}
