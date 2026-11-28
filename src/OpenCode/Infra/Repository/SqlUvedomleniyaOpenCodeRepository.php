<?php

declare(strict_types=1);

namespace App\OpenCode\Infra\Repository;

use App\OpenCode\Business\Port\UvedomleniyaOpenCodeRepository;
use App\OpenCode\Business\ReadModel\UvedomlenieONovomSoobsheniiOpenCode;
use App\OpenCode\Business\Workspace\Port\WorkspaceMirror;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Psr\Log\LoggerInterface;

final class SqlUvedomleniyaOpenCodeRepository implements UvedomleniyaOpenCodeRepository
{
    private const TABLE = 'opencode_notification';

    public function __construct(
        private Connection $connection,
        private ?WorkspaceMirror $mirror = null,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function save(UvedomlenieONovomSoobsheniiOpenCode $notification): bool
    {
        // Ключ идемпотентности — получатель + ход: один ход рассылается
        // нескольким участникам, но повторное завершение не дублирует строку
        // конкретному получателю.
        $table = self::TABLE;
        $affected = $this->connection->executeStatement(
            "insert into $table
                (id, account_id, session_id, session_type, turn_id, session_title, status, kind, created_at, read_at)
             values
                (:id, :account_id, :session_id, :session_type, :turn_id, :session_title, :status, :kind, :created_at, null)
             on conflict (turn_id, account_id) do nothing",
            [
                'id' => $notification->id,
                'account_id' => $notification->accountId,
                'session_id' => $notification->sessionId,
                'session_type' => $notification->sessionType,
                'turn_id' => $notification->turnId,
                'session_title' => $notification->sessionTitle,
                'status' => $notification->status,
                'kind' => $notification->kind,
                'created_at' => $notification->createdAt,
            ],
            [
                'id' => ParameterType::STRING,
                'account_id' => ParameterType::STRING,
                'session_id' => ParameterType::STRING,
                'session_type' => ParameterType::STRING,
                'turn_id' => ParameterType::STRING,
                'session_title' => ParameterType::STRING,
                'status' => ParameterType::STRING,
                'kind' => ParameterType::STRING,
                'created_at' => ParameterType::INTEGER,
            ]
        );

        if ($affected > 0) {
            $this->zerkalit(static fn (WorkspaceMirror $mirror) => $mirror->zerkalitUvedomlenie(
                $notification->id
            ));
        }

        return $affected > 0;
    }

    /**
     * В переходный период таблиц две: легаси `opencode_notification` и новая
     * `opencode_workspace_notification`. Чтение — union с дедупликацией по
     * `(turn_id, account_id)`: workspace-уведомление, уже скопированное
     * зеркалом в легаси, не возвращается повторно. После contract-фазы
     * достаточно убрать легаси-ветку.
     *
     * @return list<UvedomlenieONovomSoobsheniiOpenCode>
     */
    public function findNeprochitannie(string $accountId, int $limit): array
    {
        $table = self::TABLE;
        $rows = $this->connection->fetchAllAssociative(
            "select id, account_id, session_id, session_type, turn_id, session_title, status, kind, created_at, read_at
             from (
                 select id, account_id, session_id, session_type, turn_id, session_title, status, kind, created_at, read_at
                 from $table
                 where account_id = :account_id and read_at is null
                 union all
                 select w.id, w.account_id, w.workspace_id as session_id, 'workspace' as session_type,
                        w.turn_id, w.workspace_name as session_title, w.status, w.kind, w.created_at, w.read_at
                 from opencode_workspace_notification w
                 where w.account_id = :account_id and w.read_at is null
                   and not exists (
                       select 1 from $table n
                       where n.turn_id = w.turn_id and n.account_id = w.account_id
                   )
             ) u
             order by created_at, id
             limit :limit",
            ['account_id' => $accountId, 'limit' => $limit],
            ['account_id' => ParameterType::STRING, 'limit' => ParameterType::INTEGER]
        );

        return array_map(
            static fn (array $row): UvedomlenieONovomSoobsheniiOpenCode => self::izStroki($row),
            $rows
        );
    }

    public function zahvatitNeprochitannie(string $accountId, array $ids, int $now): array
    {
        $ids = array_values(array_unique($ids));

        if ($ids === []) {
            return [];
        }

        $table = self::TABLE;
        $rows = $this->connection->executeQuery(
            "update $table set read_at = :now
             where account_id = :account_id and read_at is null and id in (:ids)
             returning id",
            ['now' => $now, 'account_id' => $accountId, 'ids' => $ids],
            [
                'now' => ParameterType::INTEGER,
                'account_id' => ParameterType::STRING,
                'ids' => ArrayParameterType::STRING,
            ]
        )->fetchFirstColumn();

        $ids = array_map(static fn (mixed $id): string => (string) $id, $rows);

        // Новый агрегат — та же отметка о прочтении, чтобы union не поднял
        // уведомление повторно. Чужие строки не затрагиваются: account_id и
        // read_at is null в условии.
        $this->connection->executeStatement(
            'update opencode_workspace_notification set read_at = :now
             where account_id = :account_id and read_at is null and id in (:ids)',
            ['now' => $now, 'account_id' => $accountId, 'ids' => $ids],
            [
                'now' => ParameterType::INTEGER,
                'account_id' => ParameterType::STRING,
                'ids' => ArrayParameterType::STRING,
            ]
        );

        if ($ids !== []) {
            $this->zerkalit(static fn (WorkspaceMirror $mirror) => $mirror->zerkalitProchitannost($ids));
        }

        return $ids;
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
    private static function izStroki(array $row): UvedomlenieONovomSoobsheniiOpenCode
    {
        $kind = $row['kind'] ?? null;
        $sessionType = $row['session_type'] ?? 'unit';

        return new UvedomlenieONovomSoobsheniiOpenCode(
            (string) $row['id'],
            (string) $row['account_id'],
            (string) $row['session_id'],
            (string) $row['turn_id'],
            (string) $row['session_title'],
            (string) $row['status'],
            is_string($kind) && $kind !== '' ? $kind : null,
            (int) $row['created_at'],
            is_string($sessionType) && $sessionType !== '' ? $sessionType : 'unit',
        );
    }
}
