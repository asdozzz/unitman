<?php

namespace App\Unitman\Infra\Repository\Unit;

use App\Unitman\Business\ReadModel\Unit\SpisokUnitovReadModel;
use App\Unitman\Business\ReadModel\Unit\UnitRunnerJobs;
use Doctrine\DBAL\Connection;
use Symfony\Component\Serializer\Serializer;

final class UnitRunnerJobRepository
{
    const TABLE = 'unit_runner_jobs';
    public function __construct(private Connection $connection, private Serializer $serializer)
    {
    }

    function init(): void
    {
        $table = self::TABLE;
        $this->connection->executeQuery("create table IF NOT EXISTS $table
            (
                id serial primary key,
                unit_id varchar not null,
                job_type varchar(128) not null,
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

    /**
     * @return array<UnitRunnerJobs>
     * */
    public function findAllByUnitId(string $unitId): array
    {
        $table = self::TABLE;
        $rows = $this->connection->fetchAllAssociative("SELECT * FROM $table WHERE unit_id = :unitId", ['unitId' => $unitId]);
        if (empty($rows)) {
            return [];
        }

        return array_map(fn(array $row) => $this->makeUnitByRow($row), $rows);
    }

    function insert(UnitRunnerJobs $readModel): void
    {
        $data = [
            'unit_id' => $readModel->unitId,
            'job_type' => $readModel->jobType,
            'payload' => $this->serializer->serialize($readModel, 'json')
        ];

        $this->connection->insert(self::TABLE, $data);
    }


}
