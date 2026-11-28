<?php

declare(strict_types=1);

namespace App\OpenCode\Infra\Repository\Workspace;

use App\OpenCode\Business\Model\RolUchastnikaSessii;
use App\OpenCode\Business\Model\UchastnikSessii;
use App\OpenCode\Business\Workspace\Port\WorkspaceParticipantsRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;

final class SqlWorkspaceParticipantsRepository implements WorkspaceParticipantsRepository
{
    private const TABLE = 'opencode_workspace_participant';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function findByWorkspaceId(string $workspaceId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'select * from ' . self::TABLE
            . ' where workspace_id = :workspace_id order by created_at, account_id',
            ['workspace_id' => $workspaceId],
            ['workspace_id' => ParameterType::STRING],
        );

        return array_map(fn (array $row): UchastnikSessii => $this->make($row), $rows);
    }

    public function findByWorkspaceIds(array $workspaceIds): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('trim', $workspaceIds),
            static fn (string $id): bool => $id !== '',
        )));

        if ($ids === []) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            'select * from ' . self::TABLE
            . ' where workspace_id in (:ids) order by workspace_id, created_at, account_id',
            ['ids' => $ids],
            ['ids' => ArrayParameterType::STRING],
        );

        $result = [];

        foreach ($rows as $row) {
            $result[(string) $row['workspace_id']][] = $this->make($row);
        }

        return $result;
    }

    public function findByAccountId(string $accountId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'select * from ' . self::TABLE
            . ' where account_id = :account_id order by created_at, workspace_id',
            ['account_id' => $accountId],
            ['account_id' => ParameterType::STRING],
        );

        return array_map(fn (array $row): UchastnikSessii => $this->make($row), $rows);
    }

    public function find(string $workspaceId, string $accountId): ?UchastnikSessii
    {
        $row = $this->connection->fetchAssociative(
            'select * from ' . self::TABLE
            . ' where workspace_id = :workspace_id and account_id = :account_id',
            [
                'workspace_id' => $workspaceId,
                'account_id' => $accountId,
            ],
            [
                'workspace_id' => ParameterType::STRING,
                'account_id' => ParameterType::STRING,
            ],
        );

        if ($row === false || $row === []) {
            return null;
        }

        return $this->make($row);
    }

    public function save(UchastnikSessii $uchastnik): void
    {
        try {
            $this->connection->executeStatement(
                'insert into ' . self::TABLE . ' ('
                . 'workspace_id, account_id, role, default_credential_id, default_model, '
                . 'invited_by, created_at, updated_at'
                . ') values ('
                . ':workspace_id, :account_id, :role, :default_credential_id, :default_model, '
                . ':invited_by, :created_at, :updated_at'
                . ') on conflict (workspace_id, account_id) do update set '
                . 'role = excluded.role, '
                . 'default_credential_id = excluded.default_credential_id, '
                . 'default_model = excluded.default_model, '
                . 'updated_at = excluded.updated_at',
                [
                    'workspace_id' => $uchastnik->getSessionId(),
                    'account_id' => $uchastnik->getAccountId(),
                    'role' => $uchastnik->getRol()->value,
                    'default_credential_id' => $uchastnik->getDefaultCredentialId(),
                    'default_model' => $uchastnik->getDefaultModel(),
                    'invited_by' => $uchastnik->getInvitedBy(),
                    'created_at' => $uchastnik->getCreatedAt(),
                    'updated_at' => $uchastnik->getUpdatedAt(),
                ],
                [
                    'workspace_id' => ParameterType::STRING,
                    'account_id' => ParameterType::STRING,
                    'role' => ParameterType::STRING,
                    'default_credential_id' => ParameterType::STRING,
                    'default_model' => ParameterType::STRING,
                    'invited_by' => ParameterType::STRING,
                    'created_at' => ParameterType::INTEGER,
                    'updated_at' => ParameterType::INTEGER,
                ],
            );
        } catch (UniqueConstraintViolationException) {
            throw new \DomainException('opencode.participant.owner_conflict');
        } catch (ForeignKeyConstraintViolationException) {
            throw new \DomainException('opencode.workspace.not_found');
        }
    }

    public function udalit(UchastnikSessii $uchastnik): void
    {
        $uchastnik->trebovatMozhnoUdalit();

        $this->connection->delete(
            self::TABLE,
            [
                'workspace_id' => $uchastnik->getSessionId(),
                'account_id' => $uchastnik->getAccountId(),
            ],
            [
                'workspace_id' => ParameterType::STRING,
                'account_id' => ParameterType::STRING,
            ],
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function make(array $row): UchastnikSessii
    {
        return UchastnikSessii::restore(
            (string) $row['workspace_id'],
            (string) $row['account_id'],
            RolUchastnikaSessii::izStroki((string) $row['role']),
            $row['default_credential_id'] !== null ? (string) $row['default_credential_id'] : null,
            $row['default_model'] !== null ? (string) $row['default_model'] : null,
            $row['invited_by'] !== null ? (string) $row['invited_by'] : null,
            (int) $row['created_at'],
            (int) $row['updated_at'],
        );
    }
}
