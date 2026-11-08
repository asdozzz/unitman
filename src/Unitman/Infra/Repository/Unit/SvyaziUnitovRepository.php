<?php

declare(strict_types=1);

namespace App\Unitman\Infra\Repository\Unit;

use App\Unitman\Business\Port\Unit\UmeetPoluchatSvyaziUnitov;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Хранилище связей «переменная конфига юнита → юнит, выбранный через хелпер».
 *
 * Одна строка на переменную (`unit_id` + `variable_id` — первичный ключ),
 * поэтому замена набора связей идемпотентна.
 * `project_id` цели денормализован: фильтр списка юнитов проверяет доступность
 * проекта без джойна `spisok_unitov`.
 */
final class SvyaziUnitovRepository implements UmeetPoluchatSvyaziUnitov
{
    const TABLE = 'svyazi_unitov';

    public function __construct(private Connection $connection)
    {
    }

    function init(): void
    {
        $table = self::TABLE;
        $this->connection->executeQuery("create table IF NOT EXISTS $table
            (
                unit_id varchar(128) not null,
                variable_id varchar(128) not null,
                target_unit_id varchar(128) not null,
                project_id varchar(128) not null,
                created_at integer not null,
                primary key (unit_id, variable_id)
            );
        ");
        $this->connection->executeQuery("create index IF NOT EXISTS svyazi_unitov_target_idx on $table (target_unit_id)");
        $this->connection->executeQuery("create index IF NOT EXISTS svyazi_unitov_project_idx on $table (project_id)");
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

    function resetById(string $id): void
    {
        $this->deleteByUnit($id);
    }

    /**
     * Полная замена набора связей юнита-источника.
     *
     * `projectId` можно передать явно (известен из хелпера переменной),
     * иначе он берётся из read-модели целевого юнита.
     *
     * @param string $unitId
     * @param array<int, array{variableId: string, targetUnitId: string, projectId?: string}> $svyazi
     */
    function replaceForUnit(string $unitId, array $svyazi): void
    {
        $this->connection->delete(self::TABLE, ['unit_id' => $unitId]);

        if ($svyazi === []) {
            return;
        }

        $targetProjectIds = $this->poluchitProjectIdPoUnitam(
            array_map(fn(array $svyaz): string => $svyaz['targetUnitId'], $svyazi)
        );

        $unixtime = time();
        $table = self::TABLE;

        foreach ($svyazi as $svyaz) {
            $projectId = $svyaz['projectId'] ?? $targetProjectIds[$svyaz['targetUnitId']] ?? '';

            $this->connection->executeQuery(
                "insert into $table (unit_id, variable_id, target_unit_id, project_id, created_at)
                 values (:unitId, :variableId, :targetUnitId, :projectId, :createdAt)
                 on conflict (unit_id, variable_id) do update
                     set target_unit_id = excluded.target_unit_id,
                         project_id = excluded.project_id,
                         created_at = excluded.created_at",
                [
                    'unitId' => $unitId,
                    'variableId' => $svyaz['variableId'],
                    'targetUnitId' => $svyaz['targetUnitId'],
                    'projectId' => $projectId,
                    'createdAt' => $unixtime,
                ]
            );
        }
    }

    /**
     * @param string[] $unitIds
     * @return array<string, string>
     */
    private function poluchitProjectIdPoUnitam(array $unitIds): array
    {
        if ($unitIds === []) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            "select id, payload->>'projectId' as project_id from spisok_unitov where id in (:unitIds)",
            ['unitIds' => array_values(array_unique($unitIds))],
            ['unitIds' => ArrayParameterType::STRING]
        );

        $result = [];
        foreach ($rows as $row) {
            $result[(string)$row['id']] = (string)$row['project_id'];
        }

        return $result;
    }

    /**
     * Удаление связей, где юнит является источником и где он является целью.
     */
    function deleteByUnit(string $unitId): void
    {
        $table = self::TABLE;
        $this->connection->executeQuery(
            "delete from $table where unit_id = :unitId or target_unit_id = :unitId",
            ['unitId' => $unitId]
        );
    }

    /**
     * @return array<int, array{variableId: string, targetUnitId: string, projectId: string}>
     */
    function poluchitPoIdUnita(string $unitId): array
    {
        $table = self::TABLE;
        $rows = $this->connection->fetchAllAssociative(
            "select variable_id, target_unit_id, project_id from $table where unit_id = :unitId order by variable_id",
            ['unitId' => $unitId]
        );

        return array_map(
            fn(array $row): array => [
                'variableId' => (string)$row['variable_id'],
                'targetUnitId' => (string)$row['target_unit_id'],
                'projectId' => (string)$row['project_id'],
            ],
            $rows
        );
    }

    /**
     * @param string[] $unitIds
     * @return string[]
     */
    function poluchitSvyazannieIdUnitov(array $unitIds): array
    {
        if ($unitIds === []) {
            return [];
        }

        $table = self::TABLE;
        $rows = $this->connection->fetchFirstColumn(
            "select distinct svyaz.id from (
                 select target_unit_id as id from $table where unit_id in (:unitIds)
                 union
                 select unit_id as id from $table where target_unit_id in (:unitIds)
             ) svyaz",
            ['unitIds' => $unitIds],
            ['unitIds' => ArrayParameterType::STRING]
        );

        return array_map(fn($id): string => (string)$id, $rows);
    }
}
