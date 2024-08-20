<?php

namespace App\Utils\EventSauce\Repository;

use Doctrine\DBAL\Connection;

final class CheckpointStore
{
    private string $tableName;

    public function __construct(private Connection $connection, string $tableName)
    {
        $this->tableName = $tableName;
    }

    function resetCheckpoint(string $projectionName): void
    {
        $this->saveCheckpoint($projectionName, 0);
    }

    function addProjectionState(string $projectionName): void
    {
        $this->connection->insert($this->tableName, [
            'name' => $projectionName,
            'checkpoint' => 0
        ]);
    }

    function getCheckpoint(string $projectionName): int
    {
        $table = $this->tableName;
        $res = $this->connection->fetchAssociative('select * from '.$table.' where name=:name', ['name' => $projectionName]);
        if ($res === false || empty($res)) {
            $this->addProjectionState($projectionName);
            return 0;
        } else {
            return $res['checkpoint'];
        }
    }

    function saveCheckpoint(string $projectionName, int $checkpoint): void
    {
        $this->connection->update($this->tableName, ['checkpoint' => $checkpoint], ['name' => $projectionName]);
    }
}
