<?php

declare(strict_types=1);

namespace App\OpenCode\Infra\Repository;

use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final class SqlSessiyaOpenCodeRepository implements SessiyaOpenCodeRepository
{
    private const TABLE = 'opencode_session';

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
                'created_at' => $session->getCreatedAt(),
                'updated_at' => $session->getUpdatedAt(),
            ],
            [
                'id' => ParameterType::STRING,
                'account_id' => ParameterType::STRING,
                'credential_id' => ParameterType::STRING,
                'title' => ParameterType::STRING,
                'external_session_id' => ParameterType::STRING,
                'created_at' => ParameterType::INTEGER,
                'updated_at' => ParameterType::INTEGER,
            ]
        );
    }

    public function update(SessiyaOpenCode $session): void
    {
        $this->connection->update(
            self::TABLE,
            [
                'credential_id' => $session->getCredentialId(),
                'title' => $session->getTitle(),
                'external_session_id' => $session->getExternalSessionId(),
                'updated_at' => $session->getUpdatedAt(),
            ],
            ['id' => $session->getId()],
            [
                'credential_id' => ParameterType::STRING,
                'title' => ParameterType::STRING,
                'external_session_id' => ParameterType::STRING,
                'updated_at' => ParameterType::INTEGER,
                'id' => ParameterType::STRING,
            ]
        );
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

        return empty($row) ? null : $this->makeSession($row);
    }

    public function findByAccountId(string $accountId): array
    {
        $table = self::TABLE;
        $rows = $this->connection->fetchAllAssociative(
            "SELECT * FROM $table WHERE account_id = :account_id ORDER BY updated_at DESC, id",
            ['account_id' => $accountId]
        );

        return array_map(fn (array $row): SessiyaOpenCode => $this->makeSession($row), $rows);
    }

    public function findByCredentialId(string $credentialId): array
    {
        $table = self::TABLE;
        $rows = $this->connection->fetchAllAssociative(
            "SELECT * FROM $table WHERE credential_id = :credential_id ORDER BY updated_at DESC, id",
            ['credential_id' => $credentialId]
        );

        return array_map(fn (array $row): SessiyaOpenCode => $this->makeSession($row), $rows);
    }

    public function delete(string $id): void
    {
        $this->connection->delete(self::TABLE, ['id' => $id], ['id' => ParameterType::STRING]);
    }

    private function makeSession(array $row): SessiyaOpenCode
    {
        $externalSessionId = $row['external_session_id'] ?? null;

        return SessiyaOpenCode::restore(
            (string) $row['id'],
            (string) $row['account_id'],
            (string) $row['credential_id'],
            (string) $row['title'],
            is_string($externalSessionId) && $externalSessionId !== '' ? $externalSessionId : null,
            (int) $row['created_at'],
            (int) $row['updated_at'],
        );
    }
}