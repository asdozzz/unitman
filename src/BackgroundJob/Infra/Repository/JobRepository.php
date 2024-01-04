<?php

namespace App\BackgroundJob\Infra\Repository;

use Doctrine\DBAL\Connection;

final class JobRepository
{
    const TABLE = 'background_jobs';
    public function __construct(private Connection $connection)
    {
    }

    public function getByName(string $name): array
    {
        $table = self::TABLE;
        $res = $this->connection->fetchAssociative('select * from '.$table.' where name=:name', ['name' => $name]);

        if (empty($res)) {
            return [];
        }

        return $res;
    }

    public function getRunIdByName(string $name): string
    {
        $job = $this->getByName($name);

        if (empty($job['run_id'])) {
            throw new \Exception('Процесс не найден');
        }

        return $job['run_id'];
    }

    public function start(string $name, string $runId): void
    {
        $this->connection->update(self::TABLE, ['status' => 1, 'run_id' => $runId], ['name' => $name]);
    }

    public function pause(string $name): void
    {
        $this->connection->update(self::TABLE, ['status' => 50], ['name' => $name]);
    }

    public function unpause(string $name): void
    {
        $this->connection->update(self::TABLE, ['status' => 1], ['name' => $name]);
    }

    public function stop(string $name): void
    {
        $this->connection->update(self::TABLE, ['status' => 100, 'run_id' => null], ['name' => $name]);
    }
}
