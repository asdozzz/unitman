<?php

declare(strict_types=1);

namespace App\Unitman\Infra\Service;

use App\Unitman\Business\Model\Repo\RepoTokenHint;
use App\Unitman\Business\Port\UmeetShifrovatSekrety;
use Doctrine\DBAL\Connection;

/**
 * Opt-in миграция legacy-секретов репозиториев.
 *
 * До WS-2 event store и проекция `repo_list` хранили открытый `token`.
 * Основные кодовые пути читают старые payload'ы через upcaster и ничего не
 * переписывают. Эта миграция — единственная санкционированная перезапись
 * старых строк: она шифрует открытые значения на месте, сохраняя все
 * остальные поля.
 *
 * Требования:
 *  - выполняется только по явному `--apply` (по умолчанию dry-run);
 *  - перед изменением создаёт backup-таблицы и копирует туда затронутые строки;
 *  - идемпотентна: строки без открытого `token` не трогаются, повторный
 *    запуск возвращает 0 и не дублирует backup;
 *  - event store и repo_list меняются в ОДНОЙ транзакции.
 *
 * ВНИМАНИЕ: без ключа шифрования восстановить открытые значения из backup
 * будет невозможно. Ключ должен быть тем же, что используется приложением.
 */
final class RepoTokenEncryptionMigrator
{
    public const EVENT_STORE = 'unitman_event_store';
    public const EVENT_BACKUP = 'unitman_event_store_repo_token_backup';
    public const REPO_LIST = 'repo_list';
    public const REPO_LIST_BACKUP = 'repo_list_repo_token_backup';

    /** @var string[] */
    private const REPO_EVENT_TYPES = [
        'RepoWasAdded',
        'CredentialsOfRepoWasChanged',
    ];

    public function __construct(
        private readonly Connection $connection,
        private readonly UmeetShifrovatSekrety $shifr,
    ) {
    }

    /**
     * @return array{eventStore: int, repoList: int}
     */
    public function plan(): array
    {
        return [
            'eventStore' => $this->countLegacyEventRows(),
            'repoList' => $this->countLegacyRepoListRows(),
        ];
    }

    /**
     * @return array{eventStore: int, repoList: int}
     */
    public function apply(): array
    {
        // Если транзакция уже открыта (тесты, вложенный вызов) — участвуем в
        // ней, не создавая вложенную. Иначе оборачиваем оба шага в одну.
        $ownsTransaction = !$this->connection->isTransactionActive();

        if ($ownsTransaction) {
            $this->connection->beginTransaction();
        }

        try {
            $eventStore = $this->migrateEventStore();
            $repoList = $this->migrateRepoList();

            if ($ownsTransaction) {
                $this->connection->commit();
            }
        } catch (\Throwable $e) {
            if ($ownsTransaction) {
                $this->connection->rollBack();
            }

            throw $e;
        }

        return [
            'eventStore' => $eventStore,
            'repoList' => $repoList,
        ];
    }

    private function countLegacyEventRows(): int
    {
        $sql = sprintf(
            "select count(*) from %s where jsonb_exists(payload->'payload', 'token') and payload->'headers'->>'__event_type' in (?, ?)",
            self::EVENT_STORE,
        );

        return (int)$this->connection->fetchOne($sql, self::REPO_EVENT_TYPES);
    }

    private function countLegacyRepoListRows(): int
    {
        if (!$this->tableExists(self::REPO_LIST)) {
            return 0;
        }

        $sql = sprintf("select count(*) from %s where jsonb_exists(payload, 'token')", self::REPO_LIST);

        return (int)$this->connection->fetchOne($sql);
    }

    private function migrateEventStore(): int
    {
        $this->connection->executeStatement(sprintf(
            'create table if not exists %s as select * from %s with no data',
            self::EVENT_BACKUP,
            self::EVENT_STORE,
        ));

        $this->connection->executeStatement(sprintf(
            "insert into %s
             select s.* from %s s
             where jsonb_exists(s.payload->'payload', 'token')
               and s.payload->'headers'->>'__event_type' in (?, ?)
               and not exists (select 1 from %s b where b.id = s.id)",
            self::EVENT_BACKUP,
            self::EVENT_STORE,
            self::EVENT_BACKUP,
        ), self::REPO_EVENT_TYPES);

        $rows = $this->connection->fetchAllAssociative(sprintf(
            "select id, payload::text as payload from %s
             where jsonb_exists(payload->'payload', 'token')
               and payload->'headers'->>'__event_type' in (?, ?)
             order by id",
            self::EVENT_STORE,
        ), self::REPO_EVENT_TYPES);

        $updated = 0;

        foreach ($rows as $row) {
            $message = json_decode((string)$row['payload'], true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($message)) {
                continue;
            }

            $event = $message['payload'] ?? null;
            $plain = is_array($event) ? ($event['token'] ?? null) : null;
            $repoId = is_array($event) ? ($event['repo_id'] ?? null) : null;

            if (!is_string($plain) || $plain === '' || !is_string($repoId) || $repoId === '') {
                continue;
            }

            $event['token_cipher'] = $this->shifr->zashifrovat($plain, $repoId);
            $event['token_hint'] = RepoTokenHint::fromPlainText($plain);
            unset($event['token']);

            $message['payload'] = $event;

            $this->connection->executeStatement(
                sprintf('update %s set payload = cast(:payload as jsonb) where id = :id', self::EVENT_STORE),
                [
                    'payload' => json_encode($message, JSON_THROW_ON_ERROR),
                    'id' => $row['id'],
                ],
            );

            $updated++;
        }

        return $updated;
    }

    private function migrateRepoList(): int
    {
        if (!$this->tableExists(self::REPO_LIST)) {
            return 0;
        }

        $this->connection->executeStatement(sprintf(
            'create table if not exists %s as select * from %s with no data',
            self::REPO_LIST_BACKUP,
            self::REPO_LIST,
        ));

        $this->connection->executeStatement(sprintf(
            "insert into %s
             select s.* from %s s
             where jsonb_exists(s.payload, 'token')
               and not exists (select 1 from %s b where b.id = s.id)",
            self::REPO_LIST_BACKUP,
            self::REPO_LIST,
            self::REPO_LIST_BACKUP,
        ));

        $rows = $this->connection->fetchAllAssociative(sprintf(
            "select id, payload::text as payload from %s where jsonb_exists(payload, 'token') order by id",
            self::REPO_LIST,
        ));

        $updated = 0;

        foreach ($rows as $row) {
            $payload = json_decode((string)$row['payload'], true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($payload)) {
                continue;
            }

            $plain = $payload['token'] ?? null;

            if (!is_string($plain) || $plain === '') {
                continue;
            }

            $repoId = (string)$row['id'];
            $payload['tokenCipher'] = $this->shifr->zashifrovat($plain, $repoId);
            $payload['tokenHint'] = RepoTokenHint::fromPlainText($plain);
            unset($payload['token']);

            $this->connection->executeStatement(
                sprintf('update %s set payload = cast(:payload as jsonb) where id = :id', self::REPO_LIST),
                [
                    'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                    'id' => $row['id'],
                ],
            );

            $updated++;
        }

        return $updated;
    }

    private function tableExists(string $table): bool
    {
        return (bool)$this->connection->fetchOne('select to_regclass(:table) is not null', ['table' => $table]);
    }
}
