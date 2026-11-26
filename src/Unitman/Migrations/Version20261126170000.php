<?php

declare(strict_types=1);

namespace App\Unitman\Migrations;

use App\Unitman\Infra\Security\RepoTokenAtRestCipher;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * WS-2: шифрование legacy-токенов в read-модели `repo_list`.
 *
 * До WS-2 проекция хранила открытый `token`. Здесь строки с `payload->>'token'`
 * перешифровываются в `tokenCipher`/`tokenHint` (AES-256-GCM, AAD = id строки),
 * после чего открытый ключ удаляется. Остальные поля (включая `repoUrl`)
 * сохраняются, порядок строк не меняется.
 *
 * Идемпотентность: строки без `token` не трогаются, повторный запуск — no-op.
 * Перед изменением создаётся backup-таблица `repo_list_repo_token_backup`.
 *
 * Event store эта миграция НЕ переписывает: старые события читаются через
 * upcaster, а их перезапись — только opt-in команда
 * `app:security:encrypt-repo-tokens --apply` (см. RepoTokenEncryptionMigrator).
 *
 * down(): обратное преобразование невозможно без ключа шифрования; backup
 * сохраняется, данные не удаляются.
 */
final class Version20261126170000 extends AbstractMigration
{
    private const BACKUP = 'repo_list_repo_token_backup';

    public function getDescription(): string
    {
        return 'Unitman WS-2: шифрование legacy-токенов в repo_list';
    }

    public function up(Schema $schema): void
    {
        if ($this->tableExists('repo_list') === false) {
            return;
        }

        if ($this->legacyRowsCount() === 0) {
            return;
        }

        $rawKey = getenv('LICHNIY_TOKEN_CREDENTIALS_KEY');

        if (!is_string($rawKey) || $rawKey === '') {
            throw new \RuntimeException('repo_token.key_not_configured');
        }

        $cipher = RepoTokenAtRestCipher::fromBase64Key($rawKey);

        $this->connection->executeStatement(sprintf(
            'create table if not exists %s as select * from repo_list with no data',
            self::BACKUP,
        ));

        $this->connection->executeStatement(sprintf(
            'insert into %s
             select s.* from repo_list s
             where jsonb_exists(s.payload, \'token\')
               and not exists (select 1 from %s b where b.id = s.id)',
            self::BACKUP,
            self::BACKUP,
        ));

        $rows = $this->connection->fetchAllAssociative(
            "select id, payload::text as payload from repo_list where jsonb_exists(payload, 'token') order by id",
        );

        foreach ($rows as $row) {
            $payload = json_decode((string)$row['payload'], true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($payload)) {
                continue;
            }

            $plain = $payload['token'] ?? null;

            if (!is_string($plain) || $plain === '') {
                continue;
            }

            $payload['tokenCipher'] = $cipher->encrypt($plain, (string)$row['id']);
            $payload['tokenHint'] = $cipher->hint($plain);
            unset($payload['token']);

            $this->connection->executeStatement(
                'update repo_list set payload = cast(:payload as jsonb) where id = :id',
                [
                    'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                    'id' => $row['id'],
                ],
            );
        }
    }

    public function down(Schema $schema): void
    {
        // Открытые значения восстанавливаются только из backup-таблицы и
        // только при наличии ключа. Автоматический откат не выполняется.
    }

    private function tableExists(string $table): bool
    {
        return (bool)$this->connection->fetchOne('select to_regclass(:table) is not null', ['table' => $table]);
    }

    private function legacyRowsCount(): int
    {
        return (int)$this->connection->fetchOne(
            "select count(*) from repo_list where jsonb_exists(payload, 'token')",
        );
    }
}
