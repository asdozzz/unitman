<?php

declare(strict_types=1);

namespace App\AiChat\Infra\Repository;

use App\Account\Api\AccountApi;
use App\AiChat\Business\Model\FiltrSessiyAiChata;
use App\AiChat\Business\Model\SessiyaAiChata;
use App\AiChat\Business\Port\SessiyaAiChataRepository;
use App\AiChat\Business\ReadModel\SessiyaAiChataReadModel;
use App\AiChat\Business\ReadModel\SpisokSessiyAiChataReadModel;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/**
 * SQL-хранилище сессий AI-чата.
 *
 * Список собирается одним запросом: превью берётся `lateral`-подзапросом по
 * первому сообщению, количество — коррелированным `count`, а сами сообщения
 * служат фильтром «сессия не пустая». Признак удалённых реквизитов считается
 * по одному списку существующих id на аккаунт, без запроса на строку.
 */
final class SqlSessiyaAiChataRepository implements SessiyaAiChataRepository
{
    private const TABLE = 'ai_chat_session';
    private const MESSAGE_TABLE = 'ai_chat_message';
    private const LIKE_ESCAPE_CHAR = '!';

    public function __construct(
        private Connection $connection,
        private AccountApi $accountApi,
    ) {
    }

    public function naytiPoId(string $id): ?SessiyaAiChata
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative(
            "select * from $table where id = :id",
            ['id' => $id],
            ['id' => ParameterType::STRING],
        );

        return empty($row) ? null : $this->makeSessiya($row);
    }

    public function naytiPoIdDlyaObnovleniya(string $id): ?SessiyaAiChata
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative(
            "select * from $table where id = :id for update",
            ['id' => $id],
            ['id' => ParameterType::STRING],
        );

        return empty($row) ? null : $this->makeSessiya($row);
    }

    public function sohranit(SessiyaAiChata $sessiya): void
    {
        $table = self::TABLE;

        // Upsert не трогает account_id и created_at: владелец и дата создания
        // неизменяемы. provider_session_id и его срок сохраняются, чтобы
        // ротация provider-сессии не терялась при обычном обновлении.
        $this->connection->executeStatement(
            "insert into $table
                (id, account_id, title, provider, credential_id, model,
                 provider_session_id, provider_session_expires_at, expires_at,
                 archived_at, archive_reason, created_at, updated_at)
             values
                (:id, :account_id, :title, :provider, :credential_id, :model,
                 :provider_session_id, :provider_session_expires_at, :expires_at,
                 :archived_at, :archive_reason, :created_at, :updated_at)
             on conflict (id) do update set
                title = excluded.title,
                provider = excluded.provider,
                credential_id = excluded.credential_id,
                model = excluded.model,
                provider_session_id = excluded.provider_session_id,
                provider_session_expires_at = excluded.provider_session_expires_at,
                expires_at = excluded.expires_at,
                archived_at = excluded.archived_at,
                archive_reason = excluded.archive_reason,
                updated_at = excluded.updated_at",
            [
                'id' => $sessiya->id(),
                'account_id' => $sessiya->accountId(),
                'title' => $sessiya->title(),
                'provider' => $sessiya->provider(),
                'credential_id' => $sessiya->credentialId(),
                'model' => $sessiya->model(),
                'provider_session_id' => $sessiya->providerSessionId(),
                'provider_session_expires_at' => $sessiya->providerSessionExpiresAt(),
                'expires_at' => $sessiya->expiresAt(),
                'archived_at' => $sessiya->archivedAt(),
                'archive_reason' => $sessiya->archiveReason(),
                'created_at' => $sessiya->createdAt(),
                'updated_at' => $sessiya->updatedAt(),
            ],
            [
                'id' => ParameterType::STRING,
                'account_id' => ParameterType::STRING,
                'title' => ParameterType::STRING,
                'provider' => ParameterType::STRING,
                'credential_id' => ParameterType::STRING,
                'model' => ParameterType::STRING,
                'provider_session_id' => ParameterType::STRING,
                'provider_session_expires_at' => ParameterType::INTEGER,
                'expires_at' => ParameterType::INTEGER,
                'archived_at' => ParameterType::INTEGER,
                'archive_reason' => ParameterType::STRING,
                'created_at' => ParameterType::INTEGER,
                'updated_at' => ParameterType::INTEGER,
            ],
        );
    }

    public function spisok(string $accountId, FiltrSessiyAiChata $filtr): SpisokSessiyAiChataReadModel
    {
        $table = self::TABLE;
        $messageTable = self::MESSAGE_TABLE;

        $conditions = [
            's.account_id = :account_id',
            // Сессии без сообщений в списке не показываются.
            "exists (select 1 from $messageTable e where e.session_id = s.id)",
        ];

        $params = ['account_id' => $accountId];
        $types = ['account_id' => ParameterType::STRING];

        if (!$filtr->includeArchived) {
            $conditions[] = 's.archived_at is null';
        }

        if ($filtr->search !== null) {
            // Поиск по заголовку ИЛИ тексту первого сообщения, буквально.
            // Отдельные плейсхолдеры: PDO/pgsql не допускает повторного
            // использования одного именованного параметра в одном запросе.
            $conditions[] = "(s.title ilike :search_title escape '"
                .self::LIKE_ESCAPE_CHAR."' or p.content ilike :search_content escape '"
                .self::LIKE_ESCAPE_CHAR."')";
            $pattern = '%'.self::escapeLike($filtr->search).'%';
            $params['search_title'] = $pattern;
            $params['search_content'] = $pattern;
            $types['search_title'] = ParameterType::STRING;
            $types['search_content'] = ParameterType::STRING;
        }

        $where = implode(' and ', $conditions);

        // Одна и та же выборка используется для подсчёта total и страницы:
        // lateral-подзапрос отдаёт ровно одно первое сообщение на сессию.
        $from = "$table s
             left join lateral (
                 select content
                 from $messageTable
                 where session_id = s.id
                 order by seq asc
                 limit 1
             ) p on true";

        $total = (int) $this->connection->fetchOne(
            "select count(*) from $from where $where",
            $params,
            $types,
        );

        $params['limit'] = $filtr->limit;
        $params['offset'] = $filtr->offset;
        $types['limit'] = ParameterType::INTEGER;
        $types['offset'] = ParameterType::INTEGER;

        $rows = $this->connection->fetchAllAssociative(
            "select s.id,
                    s.title,
                    s.provider,
                    s.model,
                    s.credential_id,
                    s.archived_at,
                    s.archive_reason,
                    s.created_at,
                    s.updated_at,
                    s.expires_at,
                    p.content as preview_content,
                    (select count(*) from $messageTable c where c.session_id = s.id) as message_count
             from $from
             where $where
             order by s.updated_at desc, s.id
             limit :limit offset :offset",
            $params,
            $types,
        );

        if ($rows === []) {
            return new SpisokSessiyAiChataReadModel([], $filtr->limit, $filtr->offset, $total);
        }

        $existingCredentialIds = array_fill_keys(
            $this->accountApi->poluchitIdentifikatorySushchestvuyushchihRekvizitov($accountId),
            true,
        );

        $items = array_map(
            fn (array $row): SessiyaAiChataReadModel => $this->makeReadModel($row, $existingCredentialIds),
            $rows,
        );

        return new SpisokSessiyAiChataReadModel($items, $filtr->limit, $filtr->offset, $total);
    }

    public function arhivirovatIstekchie(string $accountId, int $now): int
    {
        $table = self::TABLE;

        return (int) $this->connection->executeStatement(
            "update $table
             set archived_at = :now_archive,
                 archive_reason = 'expired',
                 updated_at = :now_update
             where account_id = :account_id
               and expires_at <= :now_bound
               and archived_at is null",
            [
                'now_archive' => $now,
                'now_update' => $now,
                'now_bound' => $now,
                'account_id' => $accountId,
            ],
            [
                'now_archive' => ParameterType::INTEGER,
                'now_update' => ParameterType::INTEGER,
                'now_bound' => ParameterType::INTEGER,
                'account_id' => ParameterType::STRING,
            ],
        );
    }

    public function udalit(SessiyaAiChata $sessiya): void
    {
        $this->connection->transactional(function () use ($sessiya): void {
            $this->connection->delete(
                self::MESSAGE_TABLE,
                ['session_id' => $sessiya->id()],
                ['session_id' => ParameterType::STRING],
            );
            $this->connection->delete(
                self::TABLE,
                ['id' => $sessiya->id()],
                ['id' => ParameterType::STRING],
            );
        });
    }

    /**
     * @param array<string, mixed> $row
     */
    private function makeSessiya(array $row): SessiyaAiChata
    {
        return SessiyaAiChata::restore(
            (string) $row['id'],
            (string) $row['account_id'],
            (string) $row['title'],
            (string) $row['provider'],
            self::nullableString($row['credential_id'] ?? null),
            (string) $row['model'],
            self::nullableString($row['provider_session_id'] ?? null),
            self::nullableInt($row['provider_session_expires_at'] ?? null),
            (int) $row['expires_at'],
            self::nullableInt($row['archived_at'] ?? null),
            self::nullableString($row['archive_reason'] ?? null),
            (int) $row['created_at'],
            (int) $row['updated_at'],
        );
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, true>  $existingCredentialIds
     */
    private function makeReadModel(array $row, array $existingCredentialIds): SessiyaAiChataReadModel
    {
        $credentialId = self::nullableString($row['credential_id'] ?? null);
        $archivedAt = self::nullableInt($row['archived_at'] ?? null);

        return new SessiyaAiChataReadModel(
            (string) $row['id'],
            (string) $row['title'],
            (string) $row['provider'],
            (string) $row['model'],
            $archivedAt !== null,
            self::nullableString($row['archive_reason'] ?? null),
            $credentialId === null || !isset($existingCredentialIds[$credentialId]),
            SessiyaAiChataReadModel::sdelatPreview(
                \is_string($row['preview_content'] ?? null) ? $row['preview_content'] : null,
            ),
            (int) $row['created_at'],
            (int) $row['updated_at'],
            (int) $row['expires_at'],
            (int) ($row['message_count'] ?? 0),
        );
    }

    /**
     * Экранирует спецсимволы LIKE, чтобы пользовательская строка искалась буквально.
     */
    private static function escapeLike(string $value): string
    {
        return str_replace(
            [self::LIKE_ESCAPE_CHAR, '%', '_'],
            [
                self::LIKE_ESCAPE_CHAR.self::LIKE_ESCAPE_CHAR,
                self::LIKE_ESCAPE_CHAR.'%',
                self::LIKE_ESCAPE_CHAR.'_',
            ],
            $value,
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        return \is_string($value) && $value !== '' ? $value : null;
    }

    private static function nullableInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (\is_int($value)) {
            return $value;
        }

        return is_numeric($value) ? (int) $value : null;
    }
}
