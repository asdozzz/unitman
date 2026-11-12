<?php

declare(strict_types=1);

namespace App\BackgroundJob\Infra\Repository;

use Doctrine\DBAL\Connection;

/**
 * Реестр фоновых сервисов RoadRunner.
 *
 * Схема таблицы создаётся миграцией
 * App\BackgroundJob\Migrations\Version20261112000000, а не в рантайме:
 * раньше DDL выполнялся на каждом старте сервиса и опирался на тип serial,
 * который есть только в PostgreSQL.
 */
final class JobRepository
{
    private const TABLE = 'background_jobs';

    public function __construct(private Connection $connection)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function getByName(string $name): array
    {
        $res = $this->connection->fetchAssociative(
            'select * from '.self::TABLE.' where name=:name',
            ['name' => $name]
        );

        if (empty($res)) {
            return [];
        }

        return $res;
    }

    public function findRunIdByName(string $name): ?string
    {
        $job = $this->getByName($name);

        if (empty($job['run_id'])) {
            return null;
        }

        return $job['run_id'];
    }

    public function getRunIdByName(string $name): string
    {
        $job = $this->getByName($name);

        if (empty($job['run_id'])) {
            throw new \RuntimeException('job.not_found_by_name');
        }

        return $job['run_id'];
    }

    public function start(string $name, string $runId): void
    {
        $job = $this->getByName($name);

        if (!empty($job)) {
            $this->connection->update(self::TABLE, ['status' => 1, 'run_id' => $runId], ['name' => $name]);
        } else {
            $this->connection->insert(self::TABLE, ['name' => $name, 'status' => 1, 'run_id' => $runId]);
        }
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
