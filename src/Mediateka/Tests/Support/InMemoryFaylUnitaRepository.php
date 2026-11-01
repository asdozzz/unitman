<?php

declare(strict_types=1);

namespace App\Mediateka\Tests\Support;

use App\Mediateka\Business\Model\FaylUnita;
use App\Mediateka\Business\Port\FaylUnitaRepository;
use DomainException;

/**
 * Реестр файлов в памяти — замена SQL-репозитория в тестах бизнес-слоя.
 */
final class InMemoryFaylUnitaRepository implements FaylUnitaRepository
{
    /**
     * @var array<string, FaylUnita>
     */
    private array $files = [];

    public function save(FaylUnita $file): void
    {
        $this->files[$file->getId()] = $file;
    }

    public function getById(string $id): FaylUnita
    {
        $file = $this->findById($id);

        if ($file === null) {
            throw new DomainException('mediateka.file.not_found');
        }

        return $file;
    }

    public function findById(string $id): ?FaylUnita
    {
        return $this->files[$id] ?? null;
    }

    /**
     * @return FaylUnita[]
     */
    public function najtiPoUnitu(string $unitId): array
    {
        return array_values(array_filter(
            $this->files,
            fn(FaylUnita $file): bool => $file->getUnitId() === $unitId
        ));
    }

    public function prikrepitKUnitu(string $fileId, string $unitId): void
    {
        $file = $this->findById($fileId);

        if ($file === null) {
            throw new DomainException('mediateka.file.not_found');
        }

        $this->files[$fileId] = $file->prikrepitKUnitu($unitId);
    }

    /**
     * @return string[]
     */
    public function udalitPoUnitu(string $unitId): array
    {
        $deletedIds = [];

        foreach ($this->najtiPoUnitu($unitId) as $file) {
            $deletedIds[] = $file->getId();
            unset($this->files[$file->getId()]);
        }

        return $deletedIds;
    }

    public function udalit(string $id): void
    {
        unset($this->files[$id]);
    }

    public function kolichestvo(): int
    {
        return count($this->files);
    }
}
