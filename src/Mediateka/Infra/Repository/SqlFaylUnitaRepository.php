<?php

declare(strict_types=1);

namespace App\Mediateka\Infra\Repository;

use App\Mediateka\Business\Model\FaylUnita;
use App\Mediateka\Business\Port\FaylUnitaRepository;
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
            'name' => $file->getName(),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'storage_key' => $file->getStorageKey(),
            'uploaded_by' => $file->getUploadedBy(),
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

    public function prikrepitKUnitu(string $fileId, string $unitId): void
    {
        $this->connection->update(
            self::TABLE,
            ['unit_id' => $unitId],
            ['id' => $fileId]
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

    public function udalit(string $id): void
    {
        $this->connection->executeStatement(
            'DELETE FROM ' . self::TABLE . ' WHERE id = :id',
            ['id' => $id]
        );
    }
}
