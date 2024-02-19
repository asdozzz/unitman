<?php

namespace App\Unitman\Infra\Repository\Unit;

use App\Unitman\Business\Command\Unit\GetMyUnits;
use App\Unitman\Business\Command\Unit\GetUnitList;
use App\Unitman\Business\Port\Unit\CanFindUnitDouble;
use App\Unitman\Business\Port\Unit\CanGetMyUnits;
use App\Unitman\Business\Port\Unit\CanGetUnitList;
use App\Unitman\Business\ReadModel\Unit\SpisokUnitovReadModel;
use Doctrine\DBAL\Connection;

final class SpisokUnitovRepository implements CanFindUnitDouble, CanGetUnitList, CanGetMyUnits
{
    const TABLE = 'spisok_unitov';
    public function __construct(private Connection $connection)
    {
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
            'author_id' => $spisokUnitovReadModel->authorId,
            'name' => $spisokUnitovReadModel->name,
            'project_id' => $spisokUnitovReadModel->projectId,
            'project_name' => $spisokUnitovReadModel->projectName,
            'branch' => $spisokUnitovReadModel->branch,
            'state' => $spisokUnitovReadModel->state,
            'wait_result_from_runner' => $spisokUnitovReadModel->waitResultFromRunner?1:0,
            'commands' => $spisokUnitovReadModel->commands
        ];

        $this->connection->insert(self::TABLE, $data);
    }

    function update(SpisokUnitovReadModel $spisokUnitovReadModel): void
    {
        $data = [
            'name' => $spisokUnitovReadModel->name,
            'project_id' => $spisokUnitovReadModel->projectId,
            'project_name' => $spisokUnitovReadModel->projectName,
            'branch' => $spisokUnitovReadModel->branch,
            'state' => $spisokUnitovReadModel->state,
            'wait_result_from_runner' => $spisokUnitovReadModel->waitResultFromRunner?1:0,
            'commands' => $spisokUnitovReadModel->commands
        ];
        $this->connection->update(self::TABLE, $data, ['id' => $spisokUnitovReadModel->id]);
    }

    function delete(string $id): void
    {
        $this->connection->delete(self::TABLE,['id' => $id]);
    }

    function makeUnitByRow(array $row): SpisokUnitovReadModel
    {
        return new SpisokUnitovReadModel(
            $row['id'],
            $row['author_id'],
            $row['name'],
            $row['project_id'],
            $row['project_name'],
            $row['branch'],
            $row['state'],
            $row['wait_result_from_runner'],
            $row['commands'],
        );
    }

    public function isExistsDoubleByName(string $projectId, string $name): bool
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative("SELECT * FROM $table WHERE project_id = :projectId and name = :name", ['name' => $name, 'projectId' => $projectId]);
        return !empty($row);
    }

    function getList(GetUnitList $query): array
    {
        $table = self::TABLE;
        $rows = $this->connection->fetchAllAssociative("SELECT * FROM $table ORDER BY id desc LIMIT :limit OFFSET :offset",
            ['limit' => $query->limit, 'offset' => $query->offset]);

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->makeUnitByRow($row);
        }

        return $result;
    }

    function getMyUnits(GetMyUnits $command, string $authorId): array
    {
        $table = self::TABLE;
        $rows = $this->connection->fetchAllAssociative("SELECT * FROM $table where author_id = :authorId  ORDER BY id desc LIMIT :limit OFFSET :offset",
            ['limit' => $command->limit, 'offset' => $command->offset, 'authorId' => $authorId]);

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->makeUnitByRow($row);
        }

        return $result;
    }
}
