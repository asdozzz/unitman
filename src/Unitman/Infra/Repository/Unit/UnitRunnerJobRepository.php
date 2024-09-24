<?php

namespace App\Unitman\Infra\Repository\Unit;

use App\Unitman\Business\Port\Unit\CanGetUnitRunnerJobs;
use App\Unitman\Business\ReadModel\Unit\SpisokUnitovReadModel;
use App\Unitman\Business\ReadModel\Unit\UnitRunnerJob;
use App\Unitman\Business\ReadModel\Unit\UnitRunnerJobWithoutSteps;
use Doctrine\DBAL\Connection;
use Symfony\Component\Serializer\Serializer;

final class UnitRunnerJobRepository implements CanGetUnitRunnerJobs
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

    function insert(UnitRunnerJob $readModel): void
    {
        $data = [
            'unit_id' => $readModel->unitId,
            'job_type' => $readModel->jobType,
            'payload' => $this->serializer->serialize($readModel, 'json')
        ];

        $this->connection->insert(self::TABLE, $data);
    }

    /**
     * @return array<UnitRunnerJobWithoutSteps>
     * */
    function findAllRunnerJobsByUnitId(string $unitId): array
    {
        $table = self::TABLE;
        $rows = $this->connection->fetchAllAssociative("SELECT id,payload FROM $table WHERE unit_id = :unitId ORDER BY id desc", ['unitId' => $unitId]);
        if (empty($rows)) {
            return [];
        }

        return array_map(fn(array $row) => $this->makeReadModelByRowWithoutSteps($row), $rows);
    }

    function makeReadModelByRowWithoutSteps(array $row): UnitRunnerJobWithoutSteps
    {
        $unit = $this->serializer->deserialize($row['payload'], UnitRunnerJobWithoutSteps::class, 'json');
        $unit->id = $row['id'];
        return $unit;
    }

    function makeReadModelByRow(array $row): UnitRunnerJob
    {
        $unit = $this->serializer->deserialize($row['payload'], UnitRunnerJob::class, 'json');
        $unit->id = $row['id'];
        return $unit;
    }

    function getStepsByJobId(string $jobId): UnitRunnerJob
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative("SELECT id,payload FROM $table WHERE id = :id ORDER BY id desc", ['id' => $jobId]);
        if (empty($row)) {
            throw new \DomainException('unit.runner_job_not_found');
        }

        return $this->makeReadModelByRow($row);
    }
}
