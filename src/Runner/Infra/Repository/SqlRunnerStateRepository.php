<?php

namespace App\Runner\Infra\Repository;

use App\Runner\Business\Model\RunnerState;
use App\Runner\Business\Port\RunnerRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Serializer;

final class SqlRunnerStateRepository implements RunnerRepository
{
    const TABLE = 'runner_state';
    const UNITMAN_MONO_QUEUE = "unitman-mono-queue";

    public function __construct(private Connection $connection, private Serializer $serializer)
    {
    }

    function save(RunnerState $runner): void
    {
        $row = $this->findRowById($runner->getId());

        $data = $this->serializer->serialize($runner, 'json');
        if (empty($row)) {
            $this->connection->insert(self::TABLE, ['id' => $runner->getId(), 'payload' => $data]);
        } else {
            $this->connection->update(self::TABLE, [ 'payload' => $data], ['id' => $runner->getId()]);
        }
    }

    function getById(string $id): RunnerState
    {
        $row = $this->findRowById($id);

        if (empty($row)) {
            throw new \Exception('runner.state.not_found');
        }

        $object = $this->makeReadModel($row['payload']);
        return $object;
    }

    /**
     * @param string $id
     * @return false|mixed[]
     * @throws \Doctrine\DBAL\Exception
     */
    private function findRowById(string $id): array|false
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative("SELECT * from $table WHERE id = ?", [$id]);
        return $row;
    }

    function getDefaultRunnerState(): RunnerState
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative("SELECT * FROM $table LIMIT 1");

        if (empty($row)) {
            throw new \Exception('runner.state.not_found');
        }

        $object = $this->makeReadModel($row['payload']);
        return $object;
    }

    /**
     * @return RunnerState[]
     * */
    function getAll(): array
    {
        $table = self::TABLE;
        $rows = $this->connection->fetchAllAssociative("SELECT * FROM $table");

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->makeReadModel($row['payload']);
        }

        return $result;
    }

    /**
     * @param string $payload
     * @return RunnerState
     */
    private function makeReadModel(string $payload): RunnerState
    {
        $object = $this->serializer->deserialize($payload, RunnerState::class, 'json');
        return $object;
    }
}
