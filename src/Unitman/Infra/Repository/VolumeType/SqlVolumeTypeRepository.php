<?php

declare(strict_types=1);

namespace App\Unitman\Infra\Repository\VolumeType;

use App\Unitman\Business\Model\VolumeType\VolumeType;
use App\Unitman\Business\Port\VolumeType\VolumeTypeRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use DomainException;

final class SqlVolumeTypeRepository implements VolumeTypeRepository
{
    public const TABLE = 'unitman_volume_type';
    public const LINK_TABLE = 'unitman_volume_type_file';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function save(VolumeType $type): void
    {
        try {
            $this->connection->insert(self::TABLE, [
                'id' => $type->id,
                'project_id' => $type->projectId,
                'code' => $type->code,
                'name' => $type->name,
                'created_at' => $type->createdAt,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new DomainException('unit.volume_type.code_is_not_unique');
        }
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
        /** @var array<string, mixed>|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE project_id = :project_id AND code = :code',
            ['project_id' => $projectId, 'code' => trim($code)]
        );

        return $row === false ? null : VolumeType::fromRow($row);
    }

    /**
     * @return VolumeType[]
     */
    public function najtiPoProektu(string $projectId): array
    {
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE project_id = :project_id ORDER BY code ASC',
            ['project_id' => $projectId]
        );

        return array_map(static fn (array $row): VolumeType => VolumeType::fromRow($row), $rows);
    }

    public function udalit(string $typeId): void
    {
        $this->connection->executeStatement(
            'DELETE FROM ' . self::TABLE . ' WHERE id = :id',
            ['id' => $typeId]
        );
    }

    public function esliEstFayly(string $typeId): bool
    {
        /** @var int|string|false $count */
        $count = $this->connection->fetchOne(
            'SELECT count(*) FROM ' . self::LINK_TABLE . ' WHERE type_id = :type_id',
            ['type_id' => $typeId]
        );

        return (int) $count > 0;
    }

    public function udalitPoProektu(string $projectId): void
    {
        $this->connection->executeStatement(
            'DELETE FROM ' . self::LINK_TABLE . ' WHERE project_id = :project_id',
            ['project_id' => $projectId]
        );
        $this->connection->executeStatement(
            'DELETE FROM ' . self::TABLE . ' WHERE project_id = :project_id',
            ['project_id' => $projectId]
        );
    }
}
