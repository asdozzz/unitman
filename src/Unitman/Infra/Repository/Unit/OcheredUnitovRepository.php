<?php

namespace App\Unitman\Infra\Repository\Unit;

use App\Unitman\Business\ReadModel\Unit\OcheredUnitovReadModel;
use Doctrine\DBAL\Connection;
use Symfony\Component\Serializer\Serializer;

final class OcheredUnitovRepository
{
    const TABLE = 'ochered_unitov';
    const DATETIME_FORMAT = 'Y-m-d H:i:s';

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
                last_update TIMESTAMP not null
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

    function getByUnitId(string $unitId): OcheredUnitovReadModel
    {
        $model = $this->findByUnitId($unitId);

        if (empty($model)) {
            throw new \DomainException('unit.ochered_unitov.not_found');
        }

        return $model;
    }

    function insert(string $unitId, \DateTimeImmutable $lastUpdate): void
    {
        $this->connection->insert(self::TABLE, [
            'unit_id' => $unitId,
            'last_update' => $lastUpdate->format(self::DATETIME_FORMAT)
        ]);
    }

    function removeByUnitId(string $unitId): void
    {
        $this->connection->delete(self::TABLE, ['unit_id' => $unitId]);
    }

    function update(string $unitId, \DateTimeImmutable $lastUpdate): void
    {
        $this->connection->update(self::TABLE, [
            'last_update' => $lastUpdate->format(self::DATETIME_FORMAT),
        ], ['unit_id' => $unitId]);
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
            new \DateTimeImmutable($row['last_update']),
        );
    }

    /**
     * @return OcheredUnitovReadModel[]
     * */
    public function poluchitZadachiNaObrabotku(int $limit = 10): array
    {
        $table = self::TABLE;
        $rows = $this->connection->fetchAllAssociative("SELECT * FROM $table ORDER BY last_update asc LIMIT $limit for update skip locked");

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->makeModelByRow($row);
        }

        return $result;
    }

    function findByUnitId(string $unitId): OcheredUnitovReadModel|null
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative(
            "SELECT * FROM $table WHERE unit_id = :unitId",
            ['unitId' => $unitId]
        );

        if ($row) {
            return $this->makeModelByRow($row);
        }

        return null;
    }
}
