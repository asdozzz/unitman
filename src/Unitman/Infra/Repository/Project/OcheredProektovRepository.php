<?php

namespace App\Unitman\Infra\Repository\Project;

use App\Unitman\Business\ReadModel\Project\OcheredProektovReadModel;
use Doctrine\DBAL\Connection;

final class OcheredProektovRepository
{
    const TABLE = 'ochered_proektov';
    public function __construct(private Connection $connection)
    {
    }

    function init(): void
    {
        $table = self::TABLE;
        $this->connection->executeQuery("create table IF NOT EXISTS $table
            (
                id serial primary key,
                project_id varchar not null,
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

    function findByProjectIdAndQueueName(string $projectId, string $queueName): ?OcheredProektovReadModel
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative(
            "SELECT * FROM $table WHERE project_id = :projectId and queue_name = :queueName",
            ['projectId' => $projectId, 'queueName' => $queueName]
        );

        if (empty($row)) {
            return null;
        }

        return $this->makeModelByRow($row);
    }

    function insert(string $projectId, string $queueName): void
    {
        $this->connection->insert(self::TABLE, [
            'project_id' => $projectId,
            'queue_name' => $queueName
        ]);
    }


    function removeByProjectIdAndQueueName(string $projectId, string $queueName): void
    {
        $this->connection->delete(self::TABLE, ['project_id' => $projectId, 'queue_name' => $queueName]);
    }

    function removeByProjectId(string $projectId): void
    {
        $this->connection->delete(self::TABLE, ['project_id' => $projectId]);
    }

    function update(OcheredProektovReadModel $model): void
    {
        $this->connection->update(self::TABLE, [
            'project_id' => $model->projectId,
            'queue_name' => $model->queueName
        ], ['id' => $model->id]);
    }


    /**
     * @param array $row
     * @return OcheredProektovReadModel
     */
    private function makeModelByRow(array $row): OcheredProektovReadModel
    {
        return new OcheredProektovReadModel(
            (int)$row['id'],
            (string)$row['project_id'],
            (string)$row['queue_name'],
        );
    }

    /**
     * @return OcheredProektovReadModel[]
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
