<?php

declare(strict_types=1);

namespace App\Unitman\Infra\Repository\VolumeType;

use App\Unitman\Business\Model\VolumeType\VolumeTypeFile;
use App\Unitman\Business\Port\VolumeType\VolumeTypeFileRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

final class SqlVolumeTypeFileRepository implements VolumeTypeFileRepository
{
    public const TABLE = 'unitman_volume_type_file';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function save(VolumeTypeFile $link): void
    {
        $this->connection->executeStatement(
            'INSERT INTO ' . self::TABLE . ' (type_id, project_id, file_id, created_at)
             VALUES (:type_id, :project_id, :file_id, :created_at)
             ON CONFLICT (type_id, file_id) DO NOTHING',
            [
                'type_id' => $link->typeId,
                'project_id' => $link->projectId,
                'file_id' => $link->fileId,
                'created_at' => $link->createdAt,
            ]
        );
    }

    public function esliSvyazEst(string $typeId, string $fileId): bool
    {
        /** @var int|string|false $count */
        $count = $this->connection->fetchOne(
            'SELECT count(*) FROM ' . self::TABLE . ' WHERE type_id = :type_id AND file_id = :file_id',
            ['type_id' => $typeId, 'file_id' => $fileId]
        );

        return (int) $count > 0;
    }

    public function udalitSvyaz(string $typeId, string $fileId): void
    {
        $this->connection->executeStatement(
            'DELETE FROM ' . self::TABLE . ' WHERE type_id = :type_id AND file_id = :file_id',
            ['type_id' => $typeId, 'file_id' => $fileId]
        );
    }

    /**
     * @return VolumeTypeFile[]
     */
    public function najtiPoTipu(string $typeId): array
    {
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE type_id = :type_id ORDER BY created_at ASC',
            ['type_id' => $typeId]
        );

        return array_map(static fn (array $row): VolumeTypeFile => VolumeTypeFile::fromRow($row), $rows);
    }

    /**
     * @param string[] $fileIds
     *
     * @return array<string, string[]>
     */
    public function najtiKodyTipovPoFaylam(string $projectId, array $fileIds): array
    {
        $fileIds = array_values(array_unique(array_filter(
            array_map(static fn (string $fileId): string => trim($fileId), $fileIds),
            static fn (string $fileId): bool => $fileId !== ''
        )));

        if ($fileIds === []) {
            return [];
        }

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT l.file_id, t.code
             FROM ' . self::TABLE . ' l
             INNER JOIN ' . SqlVolumeTypeRepository::TABLE . ' t ON t.id = l.type_id
             WHERE l.project_id = :project_id AND l.file_id IN (:file_ids)',
            ['project_id' => $projectId, 'file_ids' => $fileIds],
            ['file_ids' => ArrayParameterType::STRING]
        );

        $result = [];
        foreach ($rows as $row) {
            $fileId = (string) $row['file_id'];
            $code = (string) $row['code'];
            $result[$fileId] ??= [];
            if (!in_array($code, $result[$fileId], true)) {
                $result[$fileId][] = $code;
            }
        }

        return $result;
    }

    public function esliFaylPrivyazanKHOTyaByOdnomuTipu(string $fileId): bool
    {
        /** @var int|string|false $count */
        $count = $this->connection->fetchOne(
            'SELECT count(*) FROM ' . self::TABLE . ' WHERE file_id = :file_id',
            ['file_id' => $fileId]
        );

        return (int) $count > 0;
    }

    public function udalitPoProektu(string $projectId): void
    {
        $this->connection->executeStatement(
            'DELETE FROM ' . self::TABLE . ' WHERE project_id = :project_id',
            ['project_id' => $projectId]
        );
    }
}
