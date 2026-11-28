<?php

declare(strict_types=1);

namespace App\OpenCode\Infra\Repository\Workspace;

use App\OpenCode\Business\ReadModel\UvedomlenieONovomSoobsheniiOpenCode;
use App\OpenCode\Business\Workspace\Port\WorkspaceNotificationsRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final class SqlWorkspaceNotificationsRepository implements WorkspaceNotificationsRepository
{
    private const TABLE = 'opencode_workspace_notification';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function save(UvedomlenieONovomSoobsheniiOpenCode $notification): bool
    {
        $table = self::TABLE;
        $affected = $this->connection->executeStatement(
            "insert into $table
                (id, account_id, workspace_id, turn_id, workspace_name, status, kind, created_at, read_at)
             values
                (:id, :account_id, :workspace_id, :turn_id, :workspace_name, :status, :kind, :created_at, null)
             on conflict (turn_id, account_id) do nothing",
            [
                'id' => $notification->id,
                'account_id' => $notification->accountId,
                'workspace_id' => $notification->sessionId,
                'turn_id' => $notification->turnId,
                'workspace_name' => $notification->sessionTitle,
                'status' => $notification->status,
                'kind' => $notification->kind,
                'created_at' => $notification->createdAt,
            ],
            [
                'id' => ParameterType::STRING,
                'account_id' => ParameterType::STRING,
                'workspace_id' => ParameterType::STRING,
                'turn_id' => ParameterType::STRING,
                'workspace_name' => ParameterType::STRING,
                'status' => ParameterType::STRING,
                'kind' => ParameterType::STRING,
                'created_at' => ParameterType::INTEGER,
            ]
        );

        return $affected > 0;
    }
}
