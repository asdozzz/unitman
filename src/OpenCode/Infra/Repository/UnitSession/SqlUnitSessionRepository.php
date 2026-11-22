<?php

declare(strict_types=1);

namespace App\OpenCode\Infra\Repository\UnitSession;

use App\OpenCode\Business\UnitSession\Model\UnitSession;
use App\OpenCode\Business\UnitSession\Model\UnitSessionFilter;
use App\OpenCode\Business\UnitSession\Port\UnitSessionRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final class SqlUnitSessionRepository implements UnitSessionRepository
{
    private const TABLE = 'opencode_unit_session';

    private const LIKE_ESCAPE_CHAR = '\\';

    public function __construct(private Connection $connection)
    {
    }

    public function save(UnitSession $session): void
    {
        $this->connection->insert(
            self::TABLE,
            [
                'id' => $session->getId(),
                'account_id' => $session->getAccountId(),
                'credential_id' => $session->getCredentialId(),
                'title' => $session->getTitle(),
                'external_session_id' => $session->getExternalSessionId(),
                'unit_id' => $session->getUnitId(),
                'unit_project_id' => $session->getUnitProjectId(),
                'archived_at' => $session->getArchivedAt(),
                'archive_reason' => $session->getArchiveReason(),
                'created_at' => $session->getCreatedAt(),
                'updated_at' => $session->getUpdatedAt(),
            ],
            [
                'id' => ParameterType::STRING,
                'account_id' => ParameterType::STRING,
                'credential_id' => ParameterType::STRING,
                'title' => ParameterType::STRING,
                'external_session_id' => ParameterType::STRING,
                'unit_id' => ParameterType::STRING,
                'unit_project_id' => ParameterType::STRING,
                'archived_at' => ParameterType::INTEGER,
                'archive_reason' => ParameterType::STRING,
                'created_at' => ParameterType::INTEGER,
                'updated_at' => ParameterType::INTEGER,
            ]
        );
    }

    public function update(UnitSession $session): void
    {
        $this->connection->update(
            self::TABLE,
            [
                'credential_id' => $session->getCredentialId(),
                'title' => $session->getTitle(),
                'external_session_id' => $session->getExternalSessionId(),
                'unit_id' => $session->getUnitId(),
                'unit_project_id' => $session->getUnitProjectId(),
                'archived_at' => $session->getArchivedAt(),
                'archive_reason' => $session->getArchiveReason(),
                'updated_at' => $session->getUpdatedAt(),
            ],
            ['id' => $session->getId()],
            [
                'credential_id' => ParameterType::STRING,
                'title' => ParameterType::STRING,
                'external_session_id' => ParameterType::STRING,
                'unit_id' => ParameterType::STRING,
                'unit_project_id' => ParameterType::STRING,
                'archived_at' => ParameterType::INTEGER,
                'archive_reason' => ParameterType::STRING,
                'updated_at' => ParameterType::INTEGER,
                'id' => ParameterType::STRING,
            ]
        );
    }

    public function getById(string $id): UnitSession
    {
        $session = $this->findById($id);

        if ($session === null) {
            throw new \DomainException('opencode.session.not_found');
        }

        return $session;
    }

    public function findById(string $id): ?UnitSession
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative(
            "select * from $table where id = :id",
            ['id' => $id]
        );

        return empty($row) ? null : $this->makeSession($row);
    }

    public function findAktivnayaPoUnitu(string $unitId): ?UnitSession
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative(
            "select * from $table
             where unit_id = :unit_id and archived_at is null
             order by updated_at desc
             limit 1",
            ['unit_id' => $unitId]
        );

        return empty($row) ? null : $this->makeSession($row);
    }

    public function findByAccountId(string $accountId, UnitSessionFilter $filter): array
    {
        $conditions = ['account_id = :account_id'];
        $params = ['account_id' => $accountId];
        $types = ['account_id' => ParameterType::STRING];

        $this->applyFilter($conditions, $params, $types, $filter);

        $table = self::TABLE;
        $rows = $this->connection->fetchAllAssociative(
            "select * from $table where " . implode(' and ', $conditions) . ' order by updated_at desc, id',
            $params,
            $types
        );

        return array_map(fn (array $row): UnitSession => $this->makeSession($row), $rows);
    }

    public function findByUnitIds(array $unitIds, UnitSessionFilter $filter): array
    {
        $unitIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): string => trim((string) $id), $unitIds),
            static fn (string $id): bool => $id !== '',
        )));

        if ($unitIds === []) {
            return [];
        }

        $conditions = ['unit_id in (:unit_ids)'];
        $params = ['unit_ids' => $unitIds];
        $types = ['unit_ids' => ArrayParameterType::STRING];

        $this->applyFilter($conditions, $params, $types, $filter);

        $table = self::TABLE;
        $rows = $this->connection->fetchAllAssociative(
            "select * from $table where " . implode(' and ', $conditions) . ' order by updated_at desc, id',
            $params,
            $types
        );

        return array_map(fn (array $row): UnitSession => $this->makeSession($row), $rows);
    }

    public function findAktivnie(int $limit): array
    {
        $table = self::TABLE;
        $rows = $this->connection->fetchAllAssociative(
            "select * from $table where archived_at is null order by updated_at desc, id limit :limit",
            ['limit' => $limit],
            ['limit' => ParameterType::INTEGER]
        );

        return array_map(fn (array $row): UnitSession => $this->makeSession($row), $rows);
    }

    /**
     * @param list<string> $conditions
     * @param array<string, mixed> $params
     * @param array<string, mixed> $types
     */
    private function applyFilter(array &$conditions, array &$params, array &$types, UnitSessionFilter $filter): void
    {
        if ($filter->onlyActive) {
            $conditions[] = 'archived_at is null';
        }

        if ($filter->estPoisk()) {
            $conditions[] = 'title like :search escape ' . self::quoteEscapeChar();
            $params['search'] = '%' . self::escapeLike(trim((string) $filter->search)) . '%';
            $types['search'] = ParameterType::STRING;
        }
    }

    private static function quoteEscapeChar(): string
    {
        return "'" . self::LIKE_ESCAPE_CHAR . "'";
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(
            [self::LIKE_ESCAPE_CHAR, '%', '_'],
            [self::LIKE_ESCAPE_CHAR . self::LIKE_ESCAPE_CHAR, self::LIKE_ESCAPE_CHAR . '%', self::LIKE_ESCAPE_CHAR . '_'],
            $value
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function makeSession(array $row): UnitSession
    {
        return UnitSession::restore(
            (string) $row['id'],
            (string) $row['account_id'],
            (string) $row['credential_id'],
            (string) $row['title'],
            self::nullableString($row['external_session_id'] ?? null),
            (string) $row['unit_id'],
            (string) $row['unit_project_id'],
            self::nullableInt($row['archived_at'] ?? null),
            self::nullableString($row['archive_reason'] ?? null),
            (int) $row['created_at'],
            (int) $row['updated_at'],
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function nullableInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        return is_numeric($value) ? (int) $value : null;
    }
}
