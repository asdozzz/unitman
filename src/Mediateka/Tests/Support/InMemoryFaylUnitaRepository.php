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

    /**
     * Архивы каталога проекта: `unitId` отсутствует, `projectId` совпадает.
     *
     * @return FaylUnita[]
     */
    public function najtiPoProektu(string $projectId): array
    {
        return array_values(array_filter(
            $this->files,
            fn(FaylUnita $file): bool => $file->getUnitId() === null
                && $file->getProjectId() === $projectId
        ));
    }

    /**
     * @return FaylUnita[]
     */
    public function najtiPoWorkspace(string $workspaceId): array
    {
        $files = array_values(array_filter(
            $this->files,
            fn(FaylUnita $file): bool => $file->getWorkspaceId() === $workspaceId
        ));

        usort($files, static fn(FaylUnita $a, FaylUnita $b): int => $a->getCreatedAt() <=> $b->getCreatedAt());

        return $files;
    }

    /**
     * @return FaylUnita[]
     */
    public function najtiPoHodu(string $turnId): array
    {
        $files = array_values(array_filter(
            $this->files,
            fn(FaylUnita $file): bool => $file->getOpencodeTurnId() === $turnId
        ));

        usort($files, static function (FaylUnita $a, FaylUnita $b): int {
            return [$a->getCreatedAt(), $a->getName()] <=> [$b->getCreatedAt(), $b->getName()];
        });

        return $files;
    }

    public function najtiPoHoduImId(string $turnId, string $fileId): ?FaylUnita
    {
        $file = $this->findById($fileId);

        if ($file === null || $file->getOpencodeTurnId() !== $turnId) {
            return null;
        }

        return $file;
    }

    public function najtiPoHoduImImeni(string $turnId, string $name): ?FaylUnita
    {
        foreach ($this->files as $file) {
            if ($file->getOpencodeTurnId() === $turnId && $file->getName() === $name) {
                return $file;
            }
        }

        return null;
    }

    /**
     * @return array{count: int, totalSize: int}
     */
    public function statistikaPoHodu(string $turnId): array
    {
        $count = 0;
        $totalSize = 0;

        foreach ($this->files as $file) {
            if ($file->getOpencodeTurnId() !== $turnId) {
                continue;
            }

            $count++;
            $totalSize += $file->getSize();
        }

        return ['count' => $count, 'totalSize' => $totalSize];
    }

    /**
     * @param string[] $workspaceIds
     *
     * @return array<string, list<FaylUnita>>
     */
    public function najtiPoWorkspaceIds(array $workspaceIds): array
    {
        $result = [];

        foreach ($workspaceIds as $workspaceId) {
            $result[$workspaceId] = $this->najtiPoWorkspace((string) $workspaceId);
        }

        return $result;
    }

    /**
     * @param string[] $fileIds
     *
     * @return FaylUnita[]
     */
    public function najtiPoImId(array $fileIds): array
    {
        $result = [];

        foreach ($fileIds as $fileId) {
            $file = $this->findById((string) $fileId);

            if ($file !== null) {
                $result[] = $file;
            }
        }

        return $result;
    }

    public function prikrepitKUnitu(string $fileId, string $unitId): void
    {
        $file = $this->findById($fileId);

        if ($file === null) {
            throw new DomainException('mediateka.file.not_found');
        }

        $this->files[$fileId] = $file->prikrepitKUnitu($unitId);
    }

    public function prikrepitKWorkspace(string $fileId, string $workspaceId): void
    {
        $file = $this->findById($fileId);

        if ($file === null) {
            throw new DomainException('mediateka.file.not_found');
        }

        $this->files[$fileId] = $file->prikrepitKWorkspace($workspaceId);
    }

    /**
     * @param string[] $fileIds
     */
    public function otmetitPrimenenie(array $fileIds, int $appliedAt): void
    {
        foreach ($fileIds as $fileId) {
            $file = $this->findById((string) $fileId);

            if ($file !== null) {
                $this->files[$fileId] = $file->primenen($appliedAt);
            }
        }
    }

    /**
     * @return string[]
     */
    public function udalitPoWorkspace(string $workspaceId): array
    {
        $deletedIds = [];

        foreach ($this->najtiPoWorkspace($workspaceId) as $file) {
            $deletedIds[] = $file->getId();
            unset($this->files[$file->getId()]);
        }

        return $deletedIds;
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
