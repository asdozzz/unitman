<?php

declare(strict_types=1);

namespace App\OpenCode\Infra\Repository;

use App\OpenCode\Business\AgentMode\Model\AgentMode;
use App\OpenCode\Business\AgentMode\Model\NastroykaRezhimaAgenta;
use App\OpenCode\Business\AgentMode\Port\AgentModeRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final class SqlAgentModeRepository implements AgentModeRepository
{
    private const MODES = 'opencode_agent_mode';
    private const USER_SETTINGS = 'opencode_agent_mode_user';
    private const DEFAULTS = 'opencode_agent_mode_default';

    public function __construct(private Connection $connection)
    {
    }

    public function findById(string $id): ?AgentMode
    {
        $row = $this->connection->fetchAssociative(
            'select * from ' . self::MODES . ' where id = :id',
            ['id' => $id],
        );

        return empty($row) ? null : $this->makeMode($row);
    }

    public function findDostupnye(string $accountId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'select * from ' . self::MODES . '
             where archived_at is null
               and (is_global = true or owner_account_id = :account_id)
             order by is_global desc, name asc, id asc',
            ['account_id' => $accountId],
        );

        return array_map(fn (array $row): AgentMode => $this->makeMode($row), $rows);
    }

    public function findGlobal(bool $includeArchived = false): array
    {
        $sql = 'select * from ' . self::MODES . ' where is_global = true';

        if (!$includeArchived) {
            $sql .= ' and archived_at is null';
        }

        $sql .= ' order by name asc, id asc';

        $rows = $this->connection->fetchAllAssociative($sql);

        return array_map(fn (array $row): AgentMode => $this->makeMode($row), $rows);
    }

    public function findGlobalBySlug(string $slug): ?AgentMode
    {
        $row = $this->connection->fetchAssociative(
            'select * from ' . self::MODES . '
             where is_global = true and slug = :slug and archived_at is null
             order by id asc limit 1',
            ['slug' => $slug],
        );

        return empty($row) ? null : $this->makeMode($row);
    }

    public function findPersonalBySlug(string $accountId, string $slug): ?AgentMode
    {
        $row = $this->connection->fetchAssociative(
            'select * from ' . self::MODES . '
             where is_global = false and owner_account_id = :account_id
               and slug = :slug and archived_at is null
             order by id asc limit 1',
            ['account_id' => $accountId, 'slug' => $slug],
        );

        return empty($row) ? null : $this->makeMode($row);
    }

    public function save(AgentMode $mode): void
    {
        $this->connection->insert(self::MODES, [
            'id' => $mode->getId(),
            'slug' => $mode->getSlug(),
            'name' => $mode->getName(),
            'description' => $mode->getDescription(),
            'prompt' => $mode->getPrompt(),
            'access_mode' => $mode->getAccessMode(),
            'is_global' => $mode->isGlobal(),
            'owner_account_id' => $mode->getOwnerAccountId(),
            'version' => $mode->getVersion(),
            'created_by' => $mode->getCreatedBy(),
            'updated_by' => $mode->getUpdatedBy(),
            'created_at' => $mode->getCreatedAt(),
            'updated_at' => $mode->getUpdatedAt(),
            'archived_at' => $mode->getArchivedAt(),
        ], [
            'id' => ParameterType::STRING,
            'slug' => ParameterType::STRING,
            'name' => ParameterType::STRING,
            'description' => ParameterType::STRING,
            'prompt' => ParameterType::STRING,
            'access_mode' => ParameterType::STRING,
            'is_global' => ParameterType::BOOLEAN,
            'owner_account_id' => ParameterType::STRING,
            'version' => ParameterType::INTEGER,
            'created_by' => ParameterType::STRING,
            'updated_by' => ParameterType::STRING,
            'created_at' => ParameterType::INTEGER,
            'updated_at' => ParameterType::INTEGER,
            'archived_at' => ParameterType::INTEGER,
        ]);
    }

    public function update(AgentMode $mode): void
    {
        $this->connection->update(self::MODES, [
            'slug' => $mode->getSlug(),
            'name' => $mode->getName(),
            'description' => $mode->getDescription(),
            'prompt' => $mode->getPrompt(),
            'access_mode' => $mode->getAccessMode(),
            'version' => $mode->getVersion(),
            'updated_by' => $mode->getUpdatedBy(),
            'updated_at' => $mode->getUpdatedAt(),
            'archived_at' => $mode->getArchivedAt(),
        ], ['id' => $mode->getId()], [
            'slug' => ParameterType::STRING,
            'name' => ParameterType::STRING,
            'description' => ParameterType::STRING,
            'prompt' => ParameterType::STRING,
            'access_mode' => ParameterType::STRING,
            'version' => ParameterType::INTEGER,
            'updated_by' => ParameterType::STRING,
            'updated_at' => ParameterType::INTEGER,
            'archived_at' => ParameterType::INTEGER,
            'id' => ParameterType::STRING,
        ]);
    }

    public function findNastroyka(string $accountId, string $modeId): ?NastroykaRezhimaAgenta
    {
        $row = $this->connection->fetchAssociative(
            'select * from ' . self::USER_SETTINGS . '
             where account_id = :account_id and mode_id = :mode_id',
            ['account_id' => $accountId, 'mode_id' => $modeId],
        );

        return empty($row) ? null : $this->makeNastroyka($row);
    }

    public function findNastroyki(string $accountId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'select * from ' . self::USER_SETTINGS . ' where account_id = :account_id',
            ['account_id' => $accountId],
        );

        $result = [];

        foreach ($rows as $row) {
            $nastroyka = $this->makeNastroyka($row);
            $result[$nastroyka->modeId] = $nastroyka;
        }

        return $result;
    }

    public function saveNastroyka(NastroykaRezhimaAgenta $nastroyka): void
    {
        // delete + insert переносим между Postgres/MariaDB/MySQL без
        // диалект-специфичного upsert.
        $this->connection->executeStatement(
            'delete from ' . self::USER_SETTINGS . '
             where account_id = :account_id and mode_id = :mode_id',
            ['account_id' => $nastroyka->accountId, 'mode_id' => $nastroyka->modeId],
        );

        $this->connection->insert(self::USER_SETTINGS, [
            'account_id' => $nastroyka->accountId,
            'mode_id' => $nastroyka->modeId,
            'credential_id' => $nastroyka->credentialId,
            'model' => $nastroyka->model,
            'enabled' => $nastroyka->enabled,
            'sort_order' => $nastroyka->sortOrder,
            'updated_at' => $nastroyka->updatedAt,
        ], [
            'account_id' => ParameterType::STRING,
            'mode_id' => ParameterType::STRING,
            'credential_id' => ParameterType::STRING,
            'model' => ParameterType::STRING,
            'enabled' => ParameterType::BOOLEAN,
            'sort_order' => ParameterType::INTEGER,
            'updated_at' => ParameterType::INTEGER,
        ]);
    }

    public function findDefaulty(string $accountId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'select chat_type, mode_id from ' . self::DEFAULTS . ' where account_id = :account_id',
            ['account_id' => $accountId],
        );

        $result = [];

        foreach ($rows as $row) {
            $result[(string) $row['chat_type']] = (string) $row['mode_id'];
        }

        return $result;
    }

    public function saveDefault(string $accountId, string $chatType, string $modeId, int $now): void
    {
        $this->connection->executeStatement(
            'delete from ' . self::DEFAULTS . '
             where account_id = :account_id and chat_type = :chat_type',
            ['account_id' => $accountId, 'chat_type' => $chatType],
        );

        $this->connection->insert(self::DEFAULTS, [
            'account_id' => $accountId,
            'chat_type' => $chatType,
            'mode_id' => $modeId,
            'updated_at' => $now,
        ], [
            'account_id' => ParameterType::STRING,
            'chat_type' => ParameterType::STRING,
            'mode_id' => ParameterType::STRING,
            'updated_at' => ParameterType::INTEGER,
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function makeMode(array $row): AgentMode
    {
        return AgentMode::restore(
            (string) $row['id'],
            (string) $row['slug'],
            (string) $row['name'],
            (string) ($row['description'] ?? ''),
            (string) $row['prompt'],
            (string) $row['access_mode'],
            (bool) $row['is_global'],
            is_string($row['owner_account_id'] ?? null) && $row['owner_account_id'] !== ''
                ? (string) $row['owner_account_id']
                : null,
            (int) $row['version'],
            (string) $row['created_by'],
            (string) $row['updated_by'],
            (int) $row['created_at'],
            (int) $row['updated_at'],
            isset($row['archived_at']) ? (int) $row['archived_at'] : null,
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function makeNastroyka(array $row): NastroykaRezhimaAgenta
    {
        return new NastroykaRezhimaAgenta(
            (string) $row['account_id'],
            (string) $row['mode_id'],
            is_string($row['credential_id'] ?? null) && $row['credential_id'] !== ''
                ? (string) $row['credential_id']
                : null,
            is_string($row['model'] ?? null) && $row['model'] !== ''
                ? (string) $row['model']
                : null,
            (bool) $row['enabled'],
            (int) $row['sort_order'],
            (int) $row['updated_at'],
        );
    }
}
