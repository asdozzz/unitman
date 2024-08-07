<?php

namespace App\Unitman\Infra\Repository\Unit;

use App\Unitman\Business\ReadModel\Unit\OcheredDlyProzesaObnovleniyaKodaPosleZapuska;
use Doctrine\DBAL\Connection;

final class OcheredDlyProzesaObnovleniyaKodaPosleZapuskaRepository
{
    const TABLE = 'ochered_dly_prozesa_obnovleniya';
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
                state varchar(128) not null
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

    function findByUnitIdAndQueueName(string $unitId, string $state): ?OcheredDlyProzesaObnovleniyaKodaPosleZapuska
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative(
            "SELECT * FROM $table WHERE unit_id = :unitId and state = :state",
            ['unitId' => $unitId, 'state' => $state]
        );

        if (empty($row)) {
            return null;
        }

        return $this->makeModelByRow($row);
    }

    function insert(string $unitId, string $state): void
    {
        $this->connection->insert(self::TABLE, [
            'unit_id' => $unitId,
            'state' => $state
        ]);
    }


    function removeByUnitIdAndQueueName(string $unitId, string $state): void
    {
        $this->connection->delete(self::TABLE, ['unit_id' => $unitId, 'state' => $state]);
    }

    function removeByUnitId(string $unitId): void
    {
        $this->connection->delete(self::TABLE, ['unit_id' => $unitId]);
    }

    function updateState(string $unitId, string $state): void
    {
        $this->connection->update(self::TABLE, [
            'unit_id' => $unitId,
            'state' => $state
        ], ['id' => $unitId]);
    }


    /**
     * @param array $row
     * @return OcheredDlyProzesaObnovleniyaKodaPosleZapuska
     */
    private function makeModelByRow(array $row): OcheredDlyProzesaObnovleniyaKodaPosleZapuska
    {
        return new OcheredDlyProzesaObnovleniyaKodaPosleZapuska(
            (int)$row['id'],
            (string)$row['unit_id'],
            (string)$row['state'],
        );
    }

    /**
     * @return OcheredDlyProzesaObnovleniyaKodaPosleZapuska[]
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
