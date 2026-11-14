<?php

declare(strict_types=1);

namespace App\Account\Infra\Repository;

use App\Account\Business\Model\OpenCode\OpenCodeCredential;
use App\Account\Business\Port\OpenCodeCredentialRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final class SqlOpenCodeCredentialRepository implements OpenCodeCredentialRepository
{
    private const TABLE = 'opencode_credential';

    public function __construct(private Connection $connection)
    {
    }

    public function save(OpenCodeCredential $credential): void
    {
        $this->connection->insert(
            self::TABLE,
            [
                'id' => $credential->getId(),
                'account_id' => $credential->getAccountId(),
                'name' => $credential->getName(),
                'url' => $credential->getUrl(),
                'model' => $credential->getModel(),
                'api_key_cipher' => $credential->getApiKeyCipher(),
                'api_key_hint' => $credential->getApiKeyHint(),
                'cipher_version' => $credential->getCipherVersion(),
                'created_at' => $credential->getCreatedAt(),
                'updated_at' => $credential->getUpdatedAt(),
            ],
            [
                'id' => ParameterType::STRING,
                'account_id' => ParameterType::STRING,
                'name' => ParameterType::STRING,
                'url' => ParameterType::STRING,
                'model' => ParameterType::STRING,
                'api_key_cipher' => ParameterType::STRING,
                'api_key_hint' => ParameterType::STRING,
                'cipher_version' => ParameterType::INTEGER,
                'created_at' => ParameterType::INTEGER,
                'updated_at' => ParameterType::INTEGER,
            ]
        );
    }

    public function update(OpenCodeCredential $credential): void
    {
        $this->connection->update(
            self::TABLE,
            [
                'name' => $credential->getName(),
                'url' => $credential->getUrl(),
                'model' => $credential->getModel(),
                'api_key_cipher' => $credential->getApiKeyCipher(),
                'api_key_hint' => $credential->getApiKeyHint(),
                'cipher_version' => $credential->getCipherVersion(),
                'updated_at' => $credential->getUpdatedAt(),
            ],
            ['id' => $credential->getId()],
            [
                'name' => ParameterType::STRING,
                'url' => ParameterType::STRING,
                'model' => ParameterType::STRING,
                'api_key_cipher' => ParameterType::STRING,
                'api_key_hint' => ParameterType::STRING,
                'cipher_version' => ParameterType::INTEGER,
                'updated_at' => ParameterType::INTEGER,
                'id' => ParameterType::STRING,
            ]
        );
    }

    public function getById(string $id): OpenCodeCredential
    {
        $credential = $this->findById($id);

        if ($credential === null) {
            throw new \DomainException('account.opencode_credential.not_found');
        }

        return $credential;
    }

    public function findById(string $id): ?OpenCodeCredential
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative(
            "SELECT * FROM $table WHERE id = :id",
            ['id' => $id]
        );

        return empty($row) ? null : $this->makeCredential($row);
    }

    public function findByName(string $accountId, string $name): ?OpenCodeCredential
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative(
            "SELECT * FROM $table WHERE account_id = :account_id AND name = :name",
            ['account_id' => $accountId, 'name' => $name]
        );

        return empty($row) ? null : $this->makeCredential($row);
    }

    public function findByAccountId(string $accountId): array
    {
        $table = self::TABLE;
        $rows = $this->connection->fetchAllAssociative(
            "SELECT * FROM $table WHERE account_id = :account_id ORDER BY name",
            ['account_id' => $accountId]
        );

        return array_map(fn (array $row): OpenCodeCredential => $this->makeCredential($row), $rows);
    }

    public function delete(string $id): void
    {
        $this->connection->delete(self::TABLE, ['id' => $id], ['id' => ParameterType::STRING]);
    }

    private function makeCredential(array $row): OpenCodeCredential
    {
        return OpenCodeCredential::restore(
            (string) $row['id'],
            (string) $row['account_id'],
            (string) $row['name'],
            (string) $row['url'],
            (string) $row['model'],
            (string) $row['api_key_cipher'],
            (string) $row['api_key_hint'],
            (int) $row['cipher_version'],
            (int) $row['created_at'],
            (int) $row['updated_at'],
        );
    }
}
