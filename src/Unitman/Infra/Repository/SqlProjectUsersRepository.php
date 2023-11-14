<?php

namespace App\Unitman\Infra\Repository;

use App\Unitman\Business\ReadModel\ProjectUsersList;
use Doctrine\DBAL\Connection;

final class SqlProjectUsersRepository
{
    const TABLE = 'project_users';
    public function __construct(private Connection $connection)
    {
    }

    function insert(ProjectUsersList $projectUser): void
    {
        $data = [
           'project_id' => $projectUser->projectId,
            'user_id' => $projectUser->userId,
            'role' => $projectUser->role
        ];
        $this->connection->insert(self::TABLE, $data);
    }

    function delete(string $projectId, string $userId): void
    {
        $data = [
            'project_id' => $projectId,
            'user_id' => $userId,
        ];
        $this->connection->delete(self::TABLE, $data);
    }

    public function findRowsById(string $projectId): ?array
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAllAssociative("SELECT * FROM $table WHERE project_id = :project_id", ['project_id' => $projectId]);
        if (empty($row)) {
            return null;
        }

        return $row;
    }
}
