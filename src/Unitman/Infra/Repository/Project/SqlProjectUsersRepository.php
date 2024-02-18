<?php

namespace App\Unitman\Infra\Repository\Project;

use App\Unitman\Business\Port\Project\UmeetPoluchatSpisokPolzovateleiProekta;
use App\Unitman\Business\Port\Project\UmeetPoluchatSpisokProektovDlyPolzovatelya;
use App\Unitman\Business\ReadModel\ProjectUsersList;
use Doctrine\DBAL\Connection;

final class SqlProjectUsersRepository implements UmeetPoluchatSpisokPolzovateleiProekta, UmeetPoluchatSpisokProektovDlyPolzovatelya
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

    private function makeProjectUserByDbRow(array $row): ProjectUsersList
    {
        return new ProjectUsersList($row['project_id'], $row['user_id'], $row['role']);
    }

    function poluchitSpisokPolzovateleiProekta(string $projectId): array
    {
        $table = self::TABLE;
        $rows = $this->connection->fetchAllAssociative("SELECT * FROM $table where project_id = :projectId", ['projectId' => $projectId]);

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->makeProjectUserByDbRow($row);
        }

        return $result;
    }

    function poluchitSpisokProektovDlyPolzovatelya(string $userId): array
    {
        $table = self::TABLE;
        $rows = $this->connection->fetchAllAssociative("SELECT * FROM $table where user_id = :userId", ['userId' => $userId]);

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->makeProjectUserByDbRow($row);
        }

        return $result;
    }
}
