<?php

namespace App\Unitman\Infra\Repository;

use App\Unitman\Business\Model\ZadachaDlyOcheredi;
use Doctrine\DBAL\Connection;

final class ZadachaDlyOcherediRepository
{
    const TABLE = 'zadachi_dly_ocheredi';

    public function __construct(private Connection $connection)
    {
    }

    function init(): void
    {
        $table = self::TABLE;
        $this->connection->executeQuery("create table IF NOT EXISTS $table
            (
                id varchar(128) not null constraint {$table}_pk primary key,
                queue_name varchar(128) not null,
                task_name varchar(128) not null,
                payload jsonb,
                attempt_for_queue int,
                retry_delay_for_queue int,
                attempt int default 0,
                error text default null
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

    function resetById(string $id): void
    {
        $table = self::TABLE;
        $this->connection->executeQuery("DELETE FROM $table where id = '$id'");
    }

    public function insert(ZadachaDlyOcheredi $model): void
    {
        $this->connection->insert(self::TABLE, [
            'id' => $model->id,
            'queue_name' => $model->queueName,
            'task_name' => $model->taskName,
            'payload' => $model->payload,
            'attempt_for_queue' => $model->attemptForRetryForQueue,
            'retry_delay_for_queue' => $model->retryDelayForQueue
        ]);
    }

    public function removeByIds(array $ids): void
    {
        $str = 'DELETE FROM ' . self::TABLE . ' WHERE id IN (\''.join('\',\'', $ids). '\')';
        $this->connection->executeQuery($str, ["ids" => '\''.join('\',\'', $ids). '\'']);
    }

    public function setSuccess(array $ids, string $error): void
    {
        $this->connection->executeQuery('UPDATE '.self::TABLE.' SET attempt=10,error=:error WHERE id IN (\''.join('\',\'', $ids). '\')',
            ['error'=>$error]);
    }

    public function setError(array $ids, string $error): void
    {
        $this->connection->executeQuery('UPDATE '.self::TABLE.' SET attempt=attempt+1,error=:error WHERE id IN (\''.join('\',\'', $ids). '\')',
            ['error'=>$error]);
    }

    private function makeModelByRow(array $row): ZadachaDlyOcheredi
    {
        return new ZadachaDlyOcheredi(
            $row['id'],
            $row['queue_name'],
            $row['task_name'],
            $row['payload'],
            $row['attempt_for_queue'],
            $row['retry_delay_for_queue'],
            $row['attempt'],
            $row['error']
        );
    }

    /**
     * @return ZadachaDlyOcheredi[]
     * */
    public function poluchitZadachiNaObrabotku(int $limit = 10): array
    {
        $this->init();
        $table = self::TABLE;
        $rows = $this->connection->fetchAllAssociative("SELECT * FROM $table WHERE attempt < 3 LIMIT $limit for update skip locked");

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->makeModelByRow($row);
        }

        return $result;
    }
}
