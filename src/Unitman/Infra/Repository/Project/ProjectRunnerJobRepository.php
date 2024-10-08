<?php

namespace App\Unitman\Infra\Repository\Project;

use App\Unitman\Business\Port\Project\CanGetProjectRunnerJobs;
use App\Unitman\Business\ReadModel\Project\ProjectRunnerJob;
use App\Unitman\Business\ReadModel\Project\ProjectRunnerJobWithoutSteps;
use Doctrine\DBAL\Connection;
use Symfony\Component\Serializer\Serializer;

final class ProjectRunnerJobRepository implements CanGetProjectRunnerJobs
{
    const TABLE = 'project_runner_jobs';
    public function __construct(private Connection $connection, private Serializer $serializer)
    {
    }

    function init(): void
    {
        $table = self::TABLE;
        $this->connection->executeQuery("create table IF NOT EXISTS $table
            (
                id serial primary key,
                project_id varchar not null,
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

    function insert(ProjectRunnerJob $readModel): void
    {
        $data = [
            'project_id' => $readModel->projectId,
            'job_type' => $readModel->jobType,
            'payload' => $this->serializer->serialize($readModel, 'json')
        ];

        $this->connection->insert(self::TABLE, $data);
    }

    /**
     * @return array<ProjectRunnerJobWithoutSteps>
     * */
    function findAllRunnerJobsByProjectId(string $projectId): array
    {
        $table = self::TABLE;
        $rows = $this->connection->fetchAllAssociative("SELECT id,payload FROM $table WHERE project_id = :projectId ORDER BY id desc", ['projectId' => $projectId]);
        if (empty($rows)) {
            return [];
        }

        return array_map(fn(array $row) => $this->makeReadModelByRowWithoutSteps($row), $rows);
    }

    function makeReadModelByRowWithoutSteps(array $row): ProjectRunnerJobWithoutSteps
    {
        $project = $this->serializer->deserialize($row['payload'], ProjectRunnerJobWithoutSteps::class, 'json');
        $project->id = $row['id'];
        return $project;
    }

    function makeReadModelByRow(array $row): ProjectRunnerJob
    {
        $project = $this->serializer->deserialize($row['payload'], ProjectRunnerJob::class, 'json');
        $project->id = $row['id'];
        return $project;
    }

    function getStepsByJobId(string $jobId): ProjectRunnerJob
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative("SELECT id,payload FROM $table WHERE id = :id ORDER BY id desc", ['id' => $jobId]);
        if (empty($row)) {
            throw new \DomainException('project.runner_job_not_found');
        }

        return $this->makeReadModelByRow($row);
    }
}
