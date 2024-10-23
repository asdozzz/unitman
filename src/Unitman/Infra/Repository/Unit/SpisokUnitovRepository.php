<?php

namespace App\Unitman\Infra\Repository\Unit;

use App\Unitman\Business\Command\Unit\GetMyUnits;
use App\Unitman\Business\Command\Unit\GetUnitList;
use App\Unitman\Business\Port\Unit\CanFindUnitDouble;
use App\Unitman\Business\Port\Unit\CanFindUnitIdByContainerName;
use App\Unitman\Business\Port\Unit\CanGetMyUnits;
use App\Unitman\Business\Port\Unit\CanGetUnitList;
use App\Unitman\Business\Port\Unit\CanGetUnitReadModelById;
use App\Unitman\Business\ReadModel\Unit\SpisokUnitovReadModel;
use Doctrine\DBAL\Connection;
use Symfony\Component\Serializer\Serializer;

final class SpisokUnitovRepository implements CanFindUnitDouble, CanGetUnitList, CanGetMyUnits, CanGetUnitReadModelById, CanFindUnitIdByContainerName
{
    const TABLE = 'spisok_unitov';
    public function __construct(private Connection $connection, private Serializer $serializer)
    {
    }

    function init(): void
    {
        $table = self::TABLE;
        $this->connection->executeQuery("create table IF NOT EXISTS $table
            (
                id varchar(128) not null constraint spisok_unitov_pk primary key,
                payload jsonb
            );
        ");
    }

    function destroy(): void
    {
        $table = self::TABLE;
        $this->connection->executeQuery("DROP TABLE IF EXISTS $table");
    }
    function truncate(): void
    {
        $table = self::TABLE;
        $this->connection->executeQuery("TRUNCATE $table");
    }

    public function findById(string $id): ?SpisokUnitovReadModel
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative("SELECT * FROM $table WHERE id = :id", ['id' => $id]);
        if (empty($row)) {
            return null;
        }

        return $this->makeUnitByRow($row);
    }

    function getById(string $unitId): SpisokUnitovReadModel
    {
        $unit = $this->findById($unitId);

        if (empty($unit)) {
            throw new \Exception('unit.spisok_unitov.unit_not_found');
        }

        return $unit;
    }

    function insert(SpisokUnitovReadModel $spisokUnitovReadModel): void
    {
        $data = [
            'id' => $spisokUnitovReadModel->id,
            'payload' => $this->serializer->serialize($spisokUnitovReadModel, 'json')
        ];

        $this->connection->insert(self::TABLE, $data);
    }

    function update(SpisokUnitovReadModel $spisokUnitovReadModel): void
    {
        $data = [
            'payload' => $this->serializer->serialize($spisokUnitovReadModel, 'json')
        ];
        $this->connection->update(self::TABLE, $data, ['id' => $spisokUnitovReadModel->id]);
    }

    function delete(string $id): void
    {
        $this->connection->delete(self::TABLE,['id' => $id]);
    }

    function makeUnitByRow(array $row): SpisokUnitovReadModel
    {
        $unit = $this->serializer->deserialize($row['payload'], SpisokUnitovReadModel::class, 'json');
        return $unit;
    }

    public function isExistsDoubleByName(string $projectId, string $unitName): bool
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative("SELECT * FROM $table WHERE payload->>'projectId' = :projectId and payload->>'name' = :name", ['name' => $unitName, 'projectId' => $projectId]);
        return !empty($row);
    }

    function getList(GetUnitList $query, string $currentUserId, array $projectIds): array
    {
        $table = self::TABLE;
        $params = ['limit' => $query->limit, 'offset' => $query->offset];
        $whereArr = [];

        if ($query->filter->onlyMine) {
            $whereArr[] = "payload->>'authorId' = :authorId";
            $params['authorId'] = $currentUserId;
        }

        if (!empty($query->filter->name)) {
            $whereArr[] = "payload->>'name' like :name";
            $params['name'] = '%'.$query->filter->name.'%';
        }

        if (!empty($query->filter->branch)) {
            $whereArr[] = "payload->>'branch' like :branch";
            $params['branch'] = '%'.$query->filter->branch.'%';
        }

        if (!empty($query->filter->projectId)) {
            $whereArr[] = "payload->>'projectId' = :projectId";
            $params['projectId'] = $query->filter->projectId;
        } else {
            $whereArr[] = "payload->>'projectId' in ('".join("','", $projectIds)."')";
        }

        $where = "WHERE ".join(' AND ', $whereArr);

        $rows = $this->connection->fetchAllAssociative("SELECT * FROM $table $where ORDER BY id desc LIMIT :limit OFFSET :offset",
            $params);

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->makeUnitByRow($row);
        }

        return $result;
    }

    function getMyUnits(GetMyUnits $command, string $authorId): array
    {
        $table = self::TABLE;
        $rows = $this->connection->fetchAllAssociative("SELECT * FROM $table where payload->>'authorId' = :authorId ORDER BY id desc LIMIT :limit OFFSET :offset",
            ['limit' => $command->limit, 'offset' => $command->offset, 'authorId' => $authorId]);

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->makeUnitByRow($row);
        }

        return $result;
    }

    /**
     * @return string[]
     * */
    function findIdsByBranch(string $projectId, string $vetka): array
    {
        $table = self::TABLE;
        $rows = $this->connection->fetchAllAssociative("SELECT id FROM $table where payload->>'projectId' = :projectId and payload->>'branch' = :branch",
            ['branch' => $vetka, 'projectId' => $projectId]);

        $result = [];
        foreach ($rows as $row) {
            $result[] = $row['id'];
        }

        return $result;
    }

    function findIdByNameAndProjectName(string $containerName): ?string
    {
        $segments = explode('.', $containerName);
        if (count($segments) !== 2) {
            return null;
        }

        $table = self::TABLE;
        $row = $this->connection->fetchAssociative("SELECT id FROM $table where payload->>'projectName' = :projectName and payload->>'name' = :name",
            ['name' => $segments[0], 'projectName' => $segments[1]]);

        return $row['id'] ?? null;
    }
}
