<?php

namespace App\Unitman\Infra\Repository\Unit;

use App\Unitman\Business\ReadModel\Unit\OcheredUnitovReadModel;
use Doctrine\DBAL\Connection;

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

    /**
     * Кладёт юнит в очередь, обновляя метку у уже стоящего.
     *
     * upsert, а не чистый insert: воркер удаляет строку, когда у юнита не
     * осталось незавершённых заданий (см. OcheredUnitovActivity), поэтому
     * событие, спроецированное сразу после удаления, обязано вернуть юнита
     * в очередь, а не упасть на конфликте. Требует уникального индекса по
     * unit_id (Version20261113120000).
     *
     * on conflict do update, а не do nothing: метка last_update определяет
     * порядок опроса, и вернувшийся в очередь юнит должен встать в конец,
     * а не сохранить старое «самое старое» время.
     */
    function insert(string $unitId, \DateTimeImmutable $lastUpdate): void
    {
        $table = self::TABLE;
        $this->connection->executeStatement(
            "INSERT INTO $table (unit_id, last_update) VALUES (:unitId, :lastUpdate)
             ON CONFLICT (unit_id) DO UPDATE SET last_update = EXCLUDED.last_update",
            [
                'unitId' => $unitId,
                'lastUpdate' => $lastUpdate->format(self::DATETIME_FORMAT),
            ]
        );
    }

    function removeByUnitId(string $unitId): void
    {
        $this->connection->delete(self::TABLE, ['unit_id' => $unitId]);
    }

    /**
     * Двигает метку юнита; отсутствующего юнита возвращает в очередь.
     *
     * Раньше это был plain update: если строки не было (воркер успел её
     * удалить, либо юнит никогда не попадал в очередь), событие молча
     * терялось, и юнит больше не опрашивался. Делегируем в upsert, чтобы
     * любое событие проекции гарантированно ставило юнита в очередь.
     */
    function update(string $unitId, \DateTimeImmutable $lastUpdate): void
    {
        $this->insert($unitId, $lastUpdate);
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
