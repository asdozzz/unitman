<?php

declare(strict_types=1);

namespace App\Account\Infra\Repository;

use App\Account\Business\Model\OpenCode\OpenCodeCredential;
use App\Account\Business\Port\OpenCodeCredentialRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/**
 * SQL-хранилище реквизитов OpenCode и их набора моделей.
 *
 * Набор моделей читается одним batch-запросом (без N+1), а запись строки
 * реквизитов и набора моделей выполняется в одной транзакции. Дефолт всегда
 * дублируется в `opencode_credential.model` — ради обратной совместимости;
 * перед установкой нового дефолта старый снимается (частичный уникальный
 * индекс допускает только один `is_default = true` на реквизиты).
 *
 * Порядок набора моделей значим (это `list<string>`): он восстанавливается по
 * колонке `position`, которая заполняется при вставке. Дефолт читается из
 * `is_default`, а не из позиции.
 */
final class SqlOpenCodeCredentialRepository implements OpenCodeCredentialRepository
{
    private const TABLE = 'opencode_credential';
    private const MODEL_TABLE = 'opencode_credential_model';

    public function __construct(private Connection $connection)
    {
    }

    public function save(OpenCodeCredential $credential): void
    {
        $this->connection->transactional(function () use ($credential): void {
            $this->connection->insert(
                self::TABLE,
                $this->credentialData($credential),
                $this->credentialTypes(),
            );

            $this->insertModels($credential);
        });
    }

    public function update(OpenCodeCredential $credential): void
    {
        $this->connection->transactional(function () use ($credential): void {
            $this->connection->update(
                self::TABLE,
                [
                    'name' => $credential->getName(),
                    'provider' => $credential->getProvider(),
                    'url' => $credential->getUrl(),
                    'model' => $credential->getDefaultModel(),
                    'api_key_cipher' => $credential->getApiKeyCipher(),
                    'api_key_hint' => $credential->getApiKeyHint(),
                    'cipher_version' => $credential->getCipherVersion(),
                    'updated_at' => $credential->getUpdatedAt(),
                ],
                ['id' => $credential->getId()],
                [
                    'name' => ParameterType::STRING,
                    'provider' => ParameterType::STRING,
                    'url' => ParameterType::STRING,
                    'model' => ParameterType::STRING,
                    'api_key_cipher' => ParameterType::STRING,
                    'api_key_hint' => ParameterType::STRING,
                    'cipher_version' => ParameterType::INTEGER,
                    'updated_at' => ParameterType::INTEGER,
                    'id' => ParameterType::STRING,
                ]
            );

            // Набор моделей заменяется целиком: сначала снимаем прежний дефолт,
            // затем удаляем старые строки и вставляем новый набор.
            $this->connection->executeStatement(
                'update ' . self::MODEL_TABLE . ' set is_default = false where credential_id = :id',
                ['id' => $credential->getId()]
            );
            $this->connection->delete(
                self::MODEL_TABLE,
                ['credential_id' => $credential->getId()],
                ['credential_id' => ParameterType::STRING]
            );

            $this->insertModels($credential);
        });
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

        if (empty($row)) {
            return null;
        }

        $models = $this->fetchModels([(string) $row['id']]);

        return $this->makeCredential($row, $models[(string) $row['id']] ?? []);
    }

    public function findByName(string $accountId, string $name): ?OpenCodeCredential
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative(
            "SELECT * FROM $table WHERE account_id = :account_id AND name = :name",
            ['account_id' => $accountId, 'name' => $name]
        );

        if (empty($row)) {
            return null;
        }

        $models = $this->fetchModels([(string) $row['id']]);

        return $this->makeCredential($row, $models[(string) $row['id']] ?? []);
    }

    public function findByAccountId(string $accountId): array
    {
        $table = self::TABLE;
        $rows = $this->connection->fetchAllAssociative(
            "SELECT * FROM $table WHERE account_id = :account_id ORDER BY name",
            ['account_id' => $accountId]
        );

        if ($rows === []) {
            return [];
        }

        $ids = array_map(static fn (array $row): string => (string) $row['id'], $rows);
        $modelsByCredential = $this->fetchModels($ids);

        return array_map(
            fn (array $row): OpenCodeCredential => $this->makeCredential(
                $row,
                $modelsByCredential[(string) $row['id']] ?? [],
            ),
            $rows,
        );
    }

    public function delete(string $id): void
    {
        $this->connection->transactional(function () use ($id): void {
            $this->connection->delete(
                self::MODEL_TABLE,
                ['credential_id' => $id],
                ['credential_id' => ParameterType::STRING]
            );
            $this->connection->delete(self::TABLE, ['id' => $id], ['id' => ParameterType::STRING]);
        });
    }

    /**
     * @param list<string> $credentialIds
     *
     * @return array<string, list<array{model: string, is_default: bool}>>
     */
    private function fetchModels(array $credentialIds): array
    {
        if ($credentialIds === []) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            'select credential_id, model, is_default from ' . self::MODEL_TABLE
            . ' where credential_id in (:ids) order by credential_id, position, model',
            ['ids' => $credentialIds],
            ['ids' => ArrayParameterType::STRING]
        );

        $grouped = [];

        foreach ($rows as $row) {
            $grouped[(string) $row['credential_id']][] = [
                'model' => (string) $row['model'],
                'is_default' => (bool) $row['is_default'],
            ];
        }

        return $grouped;
    }

    /**
     * @param array<string, mixed> $row
     * @param list<array{model: string, is_default: bool}> $modelRows
     */
    private function makeCredential(array $row, array $modelRows): OpenCodeCredential
    {
        $models = [];
        $defaultModel = '';

        foreach ($modelRows as $modelRow) {
            $models[] = $modelRow['model'];

            if ($modelRow['is_default']) {
                $defaultModel = $modelRow['model'];
            }
        }

        if ($models === []) {
            // Старые данные без строк набора: восстанавливаем из legacy-колонки.
            $legacyModel = (string) $row['model'];
            $models = [$legacyModel];
            $defaultModel = $legacyModel;
        }

        if ($defaultModel === '') {
            $defaultModel = $models[0];
        }

        return OpenCodeCredential::restore(
            (string) $row['id'],
            (string) $row['account_id'],
            (string) $row['name'],
            (string) ($row['provider'] ?? 'custom'),
            (string) $row['url'],
            $models,
            $defaultModel,
            (string) $row['api_key_cipher'],
            (string) $row['api_key_hint'],
            (int) $row['cipher_version'],
            (int) $row['created_at'],
            (int) $row['updated_at'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function credentialData(OpenCodeCredential $credential): array
    {
        return [
            'id' => $credential->getId(),
            'account_id' => $credential->getAccountId(),
            'name' => $credential->getName(),
            'provider' => $credential->getProvider(),
            'url' => $credential->getUrl(),
            'model' => $credential->getDefaultModel(),
            'api_key_cipher' => $credential->getApiKeyCipher(),
            'api_key_hint' => $credential->getApiKeyHint(),
            'cipher_version' => $credential->getCipherVersion(),
            'created_at' => $credential->getCreatedAt(),
            'updated_at' => $credential->getUpdatedAt(),
        ];
    }

    /**
     * @return array<string, ParameterType>
     */
    private function credentialTypes(): array
    {
        return [
            'id' => ParameterType::STRING,
            'account_id' => ParameterType::STRING,
            'name' => ParameterType::STRING,
            'provider' => ParameterType::STRING,
            'url' => ParameterType::STRING,
            'model' => ParameterType::STRING,
            'api_key_cipher' => ParameterType::STRING,
            'api_key_hint' => ParameterType::STRING,
            'cipher_version' => ParameterType::INTEGER,
            'created_at' => ParameterType::INTEGER,
            'updated_at' => ParameterType::INTEGER,
        ];
    }

    private function insertModels(OpenCodeCredential $credential): void
    {
        $defaultModel = $credential->getDefaultModel();
        $position = 0;

        foreach ($credential->getModels() as $model) {
            $this->connection->insert(
                self::MODEL_TABLE,
                [
                    'credential_id' => $credential->getId(),
                    'model' => $model,
                    'is_default' => $model === $defaultModel,
                    'position' => $position,
                    'created_at' => $credential->getUpdatedAt(),
                ],
                [
                    'credential_id' => ParameterType::STRING,
                    'model' => ParameterType::STRING,
                    'is_default' => ParameterType::BOOLEAN,
                    'position' => ParameterType::INTEGER,
                    'created_at' => ParameterType::INTEGER,
                ]
            );

            ++$position;
        }
    }
}
