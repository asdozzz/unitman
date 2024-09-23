<?php

namespace App\Unitman\Infra\Repository\Unit;

use App\Unitman\Business\ReadModel\Unit\OcheredUnitovReadModel;
use Doctrine\DBAL\Connection;
use Symfony\Component\Serializer\Serializer;

final class OcheredUnitovRepository
{
    const TABLE = 'ochered_unitov';
    public function __construct(private Connection $connection)
    {
    }

    function init(): void
    {
        $table = self::TABLE;
        $this->connection->executeQuery("create table IF NOT EXISTS $table
            (
                id serial primary key,
                unit_id varchar not null,
                queue_name varchar(128) not null
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

    function findByUnitIdAndQueueName(string $unitId, string $queueName): ?OcheredUnitovReadModel
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative(
            "SELECT * FROM $table WHERE unit_id = :unitId and queue_name = :queueName",
            ['unitId' => $unitId, 'queueName' => $queueName]
        );

        if (empty($row)) {
            return null;
        }

        return $this->makeModelByRow($row);
    }

    function insert(string $unitId, string $queueName): void
    {
        $this->connection->insert(self::TABLE, [
            'unit_id' => $unitId,
            'queue_name' => $queueName
        ]);
    }


    function removeByUnitIdAndQueueName(string $unitId, string $queueName): void
    {
        $this->connection->delete(self::TABLE, ['unit_id' => $unitId, 'queue_name' => $queueName]);
    }

    function removeByUnitId(string $unitId): void
    {
        $this->connection->delete(self::TABLE, ['unit_id' => $unitId]);
    }

    function update(OcheredUnitovReadModel $model): void
    {
        $this->connection->update(self::TABLE, [
            'unit_id' => $model->unitId,
            'queue_name' => $model->queueName
        ], ['id' => $model->id]);
    }


    /**
     * @param array $row
     * @return OcheredUnitovReadModel
     */
    private function makeModelByRow(array $row): OcheredUnitovReadModel
    {
        return new OcheredUnitovReadModel(
            (int)$row['id'],
            (string)$row['unit_id'],
            (string)$row['queue_name'],
        );
    }

    /**
     * @return OcheredUnitovReadModel[]
     * */
    public function poluchitZadachiNaObrabotku(int $limit = 10): array
    {
        $table = self::TABLE;
        $rows = $this->connection->fetchAllAssociative("SELECT * FROM $table LIMIT $limit");

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->makeModelByRow($row);
        }

        return $result;
    }
}
