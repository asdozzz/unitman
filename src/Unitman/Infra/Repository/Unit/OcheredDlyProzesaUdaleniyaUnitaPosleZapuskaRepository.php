<?php

namespace App\Unitman\Infra\Repository\Unit;

use App\Unitman\Business\ReadModel\Unit\OcheredDlyProzesaUdaleniyaUnitaPosleZapuska;
use Doctrine\DBAL\Connection;

final class OcheredDlyProzesaUdaleniyaUnitaPosleZapuskaRepository
{
    const TABLE = 'ochered_dly_prozesa_udaleniya';
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

    function insert(string $unitId, string $state): void
    {
        $this->connection->insert(self::TABLE, [
            'unit_id' => $unitId,
            'state' => $state
        ]);
    }

    function removeByUnitId(string $unitId): void
    {
        $this->connection->delete(self::TABLE, ['unit_id' => $unitId]);
    }

    /**
     * @param array $row
     * @return OcheredDlyProzesaUdaleniyaUnitaPosleZapuska
     */
    private function makeModelByRow(array $row): OcheredDlyProzesaUdaleniyaUnitaPosleZapuska
    {
        return new OcheredDlyProzesaUdaleniyaUnitaPosleZapuska(
            (int)$row['id'],
            (string)$row['unit_id'],
            (string)$row['state'],
        );
    }

    /**
     * @return OcheredDlyProzesaUdaleniyaUnitaPosleZapuska[]
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
