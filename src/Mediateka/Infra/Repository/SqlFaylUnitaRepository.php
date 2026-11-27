<?php

declare(strict_types=1);

namespace App\Mediateka\Infra\Repository;

use App\Mediateka\Business\Model\FaylUnita;
use App\Mediateka\Business\Port\FaylUnitaRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use DomainException;

final class SqlFaylUnitaRepository implements FaylUnitaRepository
{
    public const TABLE = 'mediateka_file';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function save(FaylUnita $file): void
    {
        $this->connection->insert(self::TABLE, [
            'id' => $file->getId(),
            'unit_id' => $file->getUnitId(),
            'project_id' => $file->getProjectId(),
            'workspace_id' => $file->getWorkspaceId(),
            'kind' => $file->getKind(),
            'name' => $file->getName(),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'storage_key' => $file->getStorageKey(),
            'uploaded_by' => $file->getUploadedBy(),
            'applied_at' => $file->getAppliedAt(),
            'opencode_turn_id' => $file->getOpencodeTurnId(),
            'created_at' => $file->getCreatedAt(),
        ]);
    }

    public function getById(string $id): FaylUnita
    {
        $file = $this->findById($id);
        if ($file === null) {
            throw new DomainException("mediateka.file.not_found: $id");
        }

        return $file;
    }

    public function findById(string $id): ?FaylUnita
    {
        /** @var array<string, mixed>|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE id = :id',
            ['id' => $id]
        );

        return $row === false ? null : FaylUnita::fromRow($row);
    }

    /**
     * @return FaylUnita[]
     */
    public function najtiPoUnitu(string $unitId): array
    {
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE unit_id = :unit_id ORDER BY created_at ASC',
            ['unit_id' => $unitId]
        );

        return array_map(static fn (array $row): FaylUnita => FaylUnita::fromRow($row), $rows);
    }

    /**
     * @return FaylUnita[]
     */
    public function najtiPoProektu(string $projectId): array
    {
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE project_id = :project_id ORDER BY created_at ASC',
            ['project_id' => $projectId]
        );

        return array_map(static fn (array $row): FaylUnita => FaylUnita::fromRow($row), $rows);
    }

    /**
     * @return FaylUnita[]
     */
    public function najtiPoWorkspace(string $workspaceId): array
    {
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE workspace_id = :workspace_id ORDER BY created_at ASC, name ASC',
            ['workspace_id' => $workspaceId]
        );

        return array_map(static fn (array $row): FaylUnita => FaylUnita::fromRow($row), $rows);
    }

    /**
     * @return FaylUnita[]
     */
    public function najtiPoHodu(string $turnId): array
    {
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE opencode_turn_id = :turn_id
             ORDER BY created_at ASC, name ASC',
            ['turn_id' => $turnId]
        );

        return array_map(static fn (array $row): FaylUnita => FaylUnita::fromRow($row), $rows);
    }

    public function najtiPoHoduImId(string $turnId, string $fileId): ?FaylUnita
    {
        /** @var array<string, mixed>|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE opencode_turn_id = :turn_id AND id = :file_id',
            ['turn_id' => $turnId, 'file_id' => $fileId]
        );

        return $row === false ? null : FaylUnita::fromRow($row);
    }

    public function najtiPoHoduImImeni(string $turnId, string $name): ?FaylUnita
    {
        /** @var array<string, mixed>|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE opencode_turn_id = :turn_id AND name = :name',
            ['turn_id' => $turnId, 'name' => $name]
        );

        return $row === false ? null : FaylUnita::fromRow($row);
    }

    /**
     * @return array{count: int, totalSize: int}
     */
    public function statistikaPoHodu(string $turnId): array
    {
        /** @var array<string, mixed>|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT count(*) AS count, coalesce(sum(size),0) AS total_size FROM ' . self::TABLE . '
             WHERE opencode_turn_id = :turn_id',
            ['turn_id' => $turnId]
        );

        if ($row === false) {
            return ['count' => 0, 'totalSize' => 0];
        }

        return [
            'count' => (int) $row['count'],
            'totalSize' => (int) $row['total_size'],
        ];
    }

    /**
     * @param string[] $workspaceIds
     *
     * @return array<string, list<FaylUnita>>
     */
    public function najtiPoWorkspaceIds(array $workspaceIds): array
    {
        $workspaceIds = array_values(array_unique(array_filter(
            array_map(static fn (string $id): string => trim($id), $workspaceIds),
            static fn (string $id): bool => $id !== '',
        )));

        if ($workspaceIds === []) {
            return [];
        }

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE workspace_id IN (:workspace_ids)
             ORDER BY workspace_id ASC, created_at ASC, name ASC',
            ['workspace_ids' => $workspaceIds],
            ['workspace_ids' => ArrayParameterType::STRING]
        );

        $result = [];
        foreach ($rows as $row) {
            $workspaceId = (string) $row['workspace_id'];
            $result[$workspaceId][] = FaylUnita::fromRow($row);
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
        $fileIds = array_values(array_unique(array_filter(
            array_map(static fn (string $id): string => trim($id), $fileIds),
            static fn (string $id): bool => $id !== '',
        )));

        if ($fileIds === []) {
            return [];
        }

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE id IN (:file_ids)',
            ['file_ids' => $fileIds],
            ['file_ids' => ArrayParameterType::STRING]
        );

        return array_map(static fn (array $row): FaylUnita => FaylUnita::fromRow($row), $rows);
    }

    public function prikrepitKUnitu(string $fileId, string $unitId): void
    {
        $this->connection->update(
            self::TABLE,
            ['unit_id' => $unitId, 'kind' => FaylUnita::KIND_UNIT],
            ['id' => $fileId]
        );
    }

    public function prikrepitKWorkspace(string $fileId, string $workspaceId): void
    {
        $this->connection->update(
            self::TABLE,
            ['workspace_id' => $workspaceId, 'kind' => FaylUnita::KIND_WORKSPACE],
            ['id' => $fileId]
        );
    }

    /**
     * @param string[] $fileIds
     */
    public function otmetitPrimenenie(array $fileIds, int $appliedAt): void
    {
        $fileIds = array_values(array_filter(
            array_map(static fn (string $id): string => trim($id), $fileIds),
            static fn (string $id): bool => $id !== '',
        ));

        if ($fileIds === []) {
            return;
        }

        $this->connection->executeStatement(
            'UPDATE ' . self::TABLE . ' SET applied_at = :applied_at WHERE id IN (:file_ids)',
            ['applied_at' => $appliedAt, 'file_ids' => $fileIds],
            ['file_ids' => ArrayParameterType::STRING]
        );
    }

    /**
     * @return string[]
     */
    public function udalitPoUnitu(string $unitId): array
    {
        $files = $this->najtiPoUnitu($unitId);
        if ($files === []) {
            return [];
        }

        $ids = array_map(static fn (FaylUnita $file): string => $file->getId(), $files);

        $this->connection->executeStatement(
            'DELETE FROM ' . self::TABLE . ' WHERE unit_id = :unit_id',
            ['unit_id' => $unitId]
        );

        return $ids;
    }

    /**
     * @return string[]
     */
    public function udalitPoWorkspace(string $workspaceId): array
    {
        $files = $this->najtiPoWorkspace($workspaceId);
        if ($files === []) {
            return [];
        }

        $ids = array_map(static fn (FaylUnita $file): string => $file->getId(), $files);

        $this->connection->executeStatement(
            'DELETE FROM ' . self::TABLE . ' WHERE workspace_id = :workspace_id',
            ['workspace_id' => $workspaceId]
        );

        return $ids;
    }

    public function udalit(string $id): void
    {
        $this->connection->executeStatement(
            'DELETE FROM ' . self::TABLE . ' WHERE id = :id',
            ['id' => $id]
        );
    }
}
