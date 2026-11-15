<?php

declare(strict_types=1);

namespace App\OpenCode\Infra\Repository;

use App\OpenCode\Business\Model\PrivyazkaKUnitu;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Model\SessiyaOpenCodeFilter;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final class SqlSessiyaOpenCodeRepository implements SessiyaOpenCodeRepository
{
    private const TABLE = 'opencode_session';

    private const UNITS_TABLE = 'opencode_session_unit';

    private const LIKE_ESCAPE_CHAR = '\\';

    public function __construct(private Connection $connection)
    {
    }

    public function save(SessiyaOpenCode $session): void
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

        $this->synchronizirovatPrivyazki($session);
    }

    public function update(SessiyaOpenCode $session): void
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

        $this->synchronizirovatPrivyazki($session);
    }

    public function getById(string $id): SessiyaOpenCode
    {
        $session = $this->findById($id);

        if ($session === null) {
            throw new \DomainException('opencode.session.not_found');
        }

        return $session;
    }

    public function findById(string $id): ?SessiyaOpenCode
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative(
            "SELECT * FROM $table WHERE id = :id",
            ['id' => $id]
        );

        if (empty($row)) {
            return null;
        }

        $privyazki = $this->zagruzitPrivyazki([(string) $row['id']]);

        return $this->makeSession($row, $privyazki[(string) $row['id']] ?? []);
    }

    public function findByAccountId(string $accountId, SessiyaOpenCodeFilter $filter): array
    {
        $table = self::TABLE;
        $conditions = ['account_id = :account_id'];
        $params = ['account_id' => $accountId];
        $types = ['account_id' => ParameterType::STRING];

        if ($filter->isOnlyActive()) {
            $conditions[] = 'archived_at is null';
        }

        if ($filter->estPoisk()) {
            $conditions[] = 'title like :search escape ' . self::quoteEscapeChar();
            $params['search'] = '%' . self::escapeLike($filter->getSearch() ?? '') . '%';
            $types['search'] = ParameterType::STRING;
        }

        $where = implode(' and ', $conditions);

        $rows = $this->connection->fetchAllAssociative(
            "SELECT * FROM $table WHERE $where ORDER BY updated_at DESC, id",
            $params,
            $types
        );

        return $this->makeSessions($rows);
    }

    public function findByCredentialId(string $credentialId): array
    {
        $table = self::TABLE;
        $rows = $this->connection->fetchAllAssociative(
            "SELECT * FROM $table WHERE credential_id = :credential_id ORDER BY updated_at DESC, id",
            ['credential_id' => $credentialId]
        );

        return $this->makeSessions($rows);
    }

    public function findAktivnieSUnitom(int $limit): array
    {
        $table = self::TABLE;
        $unitsTable = self::UNITS_TABLE;
        $rows = $this->connection->fetchAllAssociative(
            "SELECT s.* FROM $table s
             WHERE s.archived_at is null
               AND (s.unit_id is not null
                    OR exists (select 1 from $unitsTable u where u.session_id = s.id))
             ORDER BY s.updated_at ASC, s.id
             LIMIT :limit",
            ['limit' => max(1, $limit)],
            ['limit' => ParameterType::INTEGER]
        );

        return $this->makeSessions($rows);
    }

    /**
     * Полная замена списка юнитов сессии: порядок юнитов задаёт position_index,
     * нулевая позиция — рабочий каталог (cwd) хода.
     */
    private function synchronizirovatPrivyazki(SessiyaOpenCode $session): void
    {
        $this->connection->delete(
            self::UNITS_TABLE,
            ['session_id' => $session->getId()],
            ['session_id' => ParameterType::STRING]
        );

        foreach ($session->getUnits() as $position => $privyazka) {
            $this->connection->insert(
                self::UNITS_TABLE,
                [
                    'session_id' => $session->getId(),
                    'unit_id' => $privyazka->unitId,
                    'unit_project_id' => $privyazka->unitProjectId,
                    'position_index' => $position,
                    'created_at' => $session->getCreatedAt(),
                ],
                [
                    'session_id' => ParameterType::STRING,
                    'unit_id' => ParameterType::STRING,
                    'unit_project_id' => ParameterType::STRING,
                    'position_index' => ParameterType::INTEGER,
                    'created_at' => ParameterType::INTEGER,
                ]
            );
        }
    }

    /**
     * @param list<string> $sessionIds
     *
     * @return array<string, list<PrivyazkaKUnitu>>
     */
    private function zagruzitPrivyazki(array $sessionIds): array
    {
        if ($sessionIds === []) {
            return [];
        }

        $unitsTable = self::UNITS_TABLE;
        $rows = $this->connection->fetchAllAssociative(
            "SELECT session_id, unit_id, unit_project_id FROM $unitsTable
             WHERE session_id IN (:sessionIds)
             ORDER BY session_id ASC, position_index ASC, unit_id ASC",
            ['sessionIds' => $sessionIds],
            ['sessionIds' => ArrayParameterType::STRING]
        );

        $result = [];

        foreach ($rows as $row) {
            $sessionId = (string) $row['session_id'];
            $result[$sessionId] ??= [];
            $result[$sessionId][] = PrivyazkaKUnitu::izZnacheniy(
                is_string($row['unit_id']) ? $row['unit_id'] : null,
                is_string($row['unit_project_id']) ? $row['unit_project_id'] : null,
            );
        }

        return $result;
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<SessiyaOpenCode>
     */
    private function makeSessions(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $sessionIds = array_map(static fn (array $row): string => (string) $row['id'], $rows);
        $privyazki = $this->zagruzitPrivyazki($sessionIds);

        return array_map(
            fn (array $row): SessiyaOpenCode => $this->makeSession($row, $privyazki[(string) $row['id']] ?? []),
            $rows
        );
    }

    private static function quoteEscapeChar(): string
    {
        return "'" . self::LIKE_ESCAPE_CHAR . "'";
    }

    /**
     * Экранирует спецсимволы LIKE, чтобы пользовательская строка искалась буквально.
     */
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
     * @param list<PrivyazkaKUnitu> $privyazki
     */
    private function makeSession(array $row, array $privyazki): SessiyaOpenCode
    {
        $externalSessionId = $row['external_session_id'] ?? null;
        $archivedAt = $row['archived_at'] ?? null;
        $archiveReason = $row['archive_reason'] ?? null;

        return SessiyaOpenCode::restore(
            (string) $row['id'],
            (string) $row['account_id'],
            (string) $row['credential_id'],
            (string) $row['title'],
            is_string($externalSessionId) && $externalSessionId !== '' ? $externalSessionId : null,
            $privyazki !== [] ? $privyazki : $this->legacyPrivyazka($row),
            is_int($archivedAt) ? $archivedAt : (is_numeric($archivedAt) ? (int) $archivedAt : null),
            is_string($archiveReason) && $archiveReason !== '' ? $archiveReason : null,
            (int) $row['created_at'],
            (int) $row['updated_at'],
        );
    }

    /**
     * Совместимость с сессиями, привязанными до появления таблицы `opencode_session_unit`.
     *
     * @param array<string, mixed> $row
     *
     * @return list<PrivyazkaKUnitu>
     */
    private function legacyPrivyazka(array $row): array
    {
        $unitId = $row['unit_id'] ?? null;
        $unitProjectId = $row['unit_project_id'] ?? null;

        if (!is_string($unitId) || $unitId === '' || !is_string($unitProjectId) || $unitProjectId === '') {
            return [];
        }

        return [PrivyazkaKUnitu::izZnacheniy($unitId, $unitProjectId)];
    }
}
