<?php

declare(strict_types=1);

namespace App\OpenCode\Infra\Repository;

use App\OpenCode\Business\Model\RolUchastnikaSessii;
use App\OpenCode\Business\Model\UchastnikSessii;
use App\OpenCode\Business\Port\UchastnikiSessiiRepository;
use App\OpenCode\Business\Workspace\Port\WorkspaceMirror;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Psr\Log\LoggerInterface;

final class SqlUchastnikiSessiiRepository implements UchastnikiSessiiRepository
{
    private const TABLE = 'opencode_session_participant';

    public function __construct(
        private readonly Connection $connection,
        private readonly ?WorkspaceMirror $mirror = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function findBySessionId(string $sessionId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'select * from ' . self::TABLE
            . ' where session_id = :session_id order by created_at, account_id',
            ['session_id' => $sessionId],
            ['session_id' => ParameterType::STRING],
        );

        return array_map(fn (array $row): UchastnikSessii => $this->make($row), $rows);
    }

    public function findBySessionIds(array $sessionIds): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('trim', $sessionIds),
            static fn (string $id): bool => $id !== '',
        )));

        if ($ids === []) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            'select * from ' . self::TABLE
            . ' where session_id in (:ids) order by session_id, created_at, account_id',
            ['ids' => $ids],
            ['ids' => ArrayParameterType::STRING],
        );

        $result = [];

        foreach ($rows as $row) {
            $result[(string) $row['session_id']][] = $this->make($row);
        }

        return $result;
    }

    public function findByAccountId(string $accountId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'select * from ' . self::TABLE
            . ' where account_id = :account_id order by created_at, session_id',
            ['account_id' => $accountId],
            ['account_id' => ParameterType::STRING],
        );

        return array_map(fn (array $row): UchastnikSessii => $this->make($row), $rows);
    }

    public function find(string $sessionId, string $accountId): ?UchastnikSessii
    {
        $row = $this->connection->fetchAssociative(
            'select * from ' . self::TABLE
            . ' where session_id = :session_id and account_id = :account_id',
            [
                'session_id' => $sessionId,
                'account_id' => $accountId,
            ],
            [
                'session_id' => ParameterType::STRING,
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
                . 'session_id, account_id, role, default_credential_id, default_model, '
                . 'invited_by, created_at, updated_at'
                . ') values ('
                . ':session_id, :account_id, :role, :default_credential_id, :default_model, '
                . ':invited_by, :created_at, :updated_at'
                . ') on conflict (session_id, account_id) do update set '
                . 'role = excluded.role, '
                . 'default_credential_id = excluded.default_credential_id, '
                . 'default_model = excluded.default_model, '
                . 'updated_at = excluded.updated_at',
                [
                    'session_id' => $uchastnik->getSessionId(),
                    'account_id' => $uchastnik->getAccountId(),
                    'role' => $uchastnik->getRol()->value,
                    'default_credential_id' => $uchastnik->getDefaultCredentialId(),
                    'default_model' => $uchastnik->getDefaultModel(),
                    'invited_by' => $uchastnik->getInvitedBy(),
                    'created_at' => $uchastnik->getCreatedAt(),
                    'updated_at' => $uchastnik->getUpdatedAt(),
                ],
                [
                    'session_id' => ParameterType::STRING,
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
            // Частичный уникальный индекс допускает ровно одного owner на
            // сессию: нарушение означает попытку назначить второго владельца.
            throw new \DomainException('opencode.participant.owner_conflict');
        } catch (ForeignKeyConstraintViolationException) {
            throw new \DomainException('opencode.session.not_found');
        }

        $this->zerkalit(static fn (WorkspaceMirror $mirror) => $mirror->zerkalitUchastnika(
            $uchastnik->getSessionId(),
            $uchastnik->getAccountId(),
        ));
    }

    public function udalit(UchastnikSessii $uchastnik): void
    {
        $uchastnik->trebovatMozhnoUdalit();

        $this->connection->delete(
            self::TABLE,
            [
                'session_id' => $uchastnik->getSessionId(),
                'account_id' => $uchastnik->getAccountId(),
            ],
            [
                'session_id' => ParameterType::STRING,
                'account_id' => ParameterType::STRING,
            ],
        );

        $this->zerkalit(static fn (WorkspaceMirror $mirror) => $mirror->udalitUchastnika(
            $uchastnik->getSessionId(),
            $uchastnik->getAccountId(),
        ));
    }

    /**
     * Best-effort зеркало: ошибка не должна ломать легаси-операцию.
     */
    private function zerkalit(callable $deistvie): void
    {
        if ($this->mirror === null || !$this->mirror->isEnabled()) {
            return;
        }

        try {
            $deistvie($this->mirror);
        } catch (\Throwable $exception) {
            $this->logger?->error('opencode.workspace.mirror_failed', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function make(array $row): UchastnikSessii
    {
        return UchastnikSessii::restore(
            (string) $row['session_id'],
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
