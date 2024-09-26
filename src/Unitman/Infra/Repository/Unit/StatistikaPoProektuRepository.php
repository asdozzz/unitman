<?php

namespace App\Unitman\Infra\Repository\Unit;

use App\Unitman\Business\ReadModel\Dashboard\StatistikaPoProektu;
use Doctrine\DBAL\Connection;
use Symfony\Component\Serializer\Serializer;

final class StatistikaPoProektuRepository
{
    const TABLE = 'statistika_po_proektu';
    public function __construct(private Connection $connection, private Serializer $serializer)
    {
    }

    function init(): void
    {
        $table = self::TABLE;
        $this->connection->executeQuery("create table IF NOT EXISTS $table
            (
                id varchar(128) not null constraint statistika_po_proektu_pk primary key,
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

    function makeUnitByRow(array $row): StatistikaPoProektu
    {
        $unit = $this->serializer->deserialize($row['payload'], StatistikaPoProektu::class, 'json');
        return $unit;
    }

    /**
     * @return StatistikaPoProektu[]
     * */
    function getList(): array
    {
        $table = self::TABLE;


        $rows = $this->connection->fetchAllAssociative("SELECT * FROM $table ORDER BY id desc ");

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->makeUnitByRow($row);
        }

        return $result;
    }

    public function findById(string $id): ?StatistikaPoProektu
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative("SELECT * FROM $table WHERE id = :id", ['id' => $id]);
        if (empty($row)) {
            return null;
        }

        return $this->makeUnitByRow($row);
    }

    function insert(StatistikaPoProektu $model): void
    {
        $data = [
            'id' => $model->projectId,
            'payload' => $this->serializer->serialize($model, 'json')
        ];

        $this->connection->insert(self::TABLE, $data);
    }

    function update(StatistikaPoProektu $model): void
    {
        $data = [
            'payload' => $this->serializer->serialize($model, 'json')
        ];

        $this->connection->update(self::TABLE, $data, ['id' => $model->projectId]);
    }

    function removeById(string $id): void
    {
        $this->connection->delete(self::TABLE, ['id' => $id]);
    }
}
