<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Support;

use App\Unitman\Business\Model\VolumeType\VolumeTypeFile;
use App\Unitman\Business\Port\VolumeType\VolumeTypeFileRepository;

/**
 * Связи «тип тома — архив медиатеки» в памяти.
 */
final class InMemoryVolumeTypeFileRepository implements VolumeTypeFileRepository
{
    public function __construct(private readonly InMemoryKatalogStorage $storage)
    {
    }

    public function save(VolumeTypeFile $link): void
    {
        if ($this->esliSvyazEst($link->typeId, $link->fileId)) {
            return;
        }

        $this->storage->links[] = $link;
    }

    public function esliSvyazEst(string $typeId, string $fileId): bool
    {
        foreach ($this->storage->links as $link) {
            if ($link->typeId === $typeId && $link->fileId === $fileId) {
                return true;
            }
        }

        return false;
    }

    public function udalitSvyaz(string $typeId, string $fileId): void
    {
        $this->storage->links = array_values(array_filter(
            $this->storage->links,
            static fn (VolumeTypeFile $link): bool => !($link->typeId === $typeId && $link->fileId === $fileId)
        ));
    }

    /**
     * @return VolumeTypeFile[]
     */
    public function najtiPoTipu(string $typeId): array
    {
        return array_values(array_filter(
            $this->storage->links,
            static fn (VolumeTypeFile $link): bool => $link->typeId === $typeId
        ));
    }

    /**
     * @param string[] $fileIds
     *
     * @return array<string, string[]>
     */
    public function najtiKodyTipovPoFaylam(string $projectId, array $fileIds): array
    {
        $kodyPoFaylu = [];

        foreach ($this->storage->links as $link) {
            if ($link->projectId !== $projectId) {
                continue;
            }

            if (!in_array($link->fileId, $fileIds, true)) {
                continue;
            }

            $type = $this->storage->types[$link->typeId] ?? null;
            if ($type === null) {
                continue;
            }

            $kodyPoFaylu[$link->fileId][] = $type->code;
        }

        return $kodyPoFaylu;
    }

    public function esliFaylPrivyazanKHOTyaByOdnomuTipu(string $fileId): bool
    {
        foreach ($this->storage->links as $link) {
            if ($link->fileId === $fileId) {
                return true;
            }
        }

        return false;
    }

    public function udalitPoProektu(string $projectId): void
    {
        $this->storage->links = array_values(array_filter(
            $this->storage->links,
            static fn (VolumeTypeFile $link): bool => $link->projectId !== $projectId
        ));
    }
}
