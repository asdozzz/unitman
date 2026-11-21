<?php

declare(strict_types=1);

namespace App\Unitman\Infra\Repository\LichniyToken;

use App\Unitman\Business\Port\LichniyToken\LichniyTokenListRepository;
use App\Unitman\Business\ReadModel\LichniyToken\LichniyTokenList;
use Doctrine\DBAL\Connection;

final class SqlLichniyTokenListRepository implements LichniyTokenListRepository
{
    private const TABLE = 'lichniy_token';

    public function __construct(private Connection $connection)
    {
    }

    public function init(): void
    {
        $this->connection->executeQuery("create table IF NOT EXISTS " . self::TABLE . "
            (
                id                varchar(128) not null constraint lichniy_token_pk primary key,
                account_id        varchar(128) not null,
                repo_id           varchar(128) not null,
                nazvanie          varchar(255) not null,
                token_cipher      text         not null,
                token_hint        varchar(64)  not null,
                obyom_prav        varchar(32)  not null,
                proekty_s_pushom  jsonb        not null default '[]',
                provereno_v       integer      not null,
                deleted           boolean      not null default false
            );
        ");
        $this->connection->executeQuery(
            "create index IF NOT EXISTS lichniy_token_account_repo_idx on " . self::TABLE . " (account_id, repo_id);"
        );
    }

    public function destroy(): void
    {
        $this->connection->executeQuery('DROP TABLE IF EXISTS ' . self::TABLE);
    }

    public function truncate(): void
    {
        $this->connection->executeQuery('TRUNCATE ' . self::TABLE);
    }

    public function resetById(string $id): void
    {
        $this->connection->executeQuery(
            'DELETE FROM ' . self::TABLE . ' where id = :id',
            ['id' => $id],
        );
    }

    public function insert(LichniyTokenList $token): void
    {
        $this->connection->insert(self::TABLE, [
            'id' => $token->id,
            'account_id' => $token->accountId,
            'repo_id' => $token->repoId,
            'nazvanie' => $token->nazvanie,
            'token_cipher' => $token->tokenCipher,
            'token_hint' => $token->tokenHint,
            'obyom_prav' => $token->obyomPrav,
            'proekty_s_pushom' => json_encode($token->proektySPushom, JSON_THROW_ON_ERROR),
            'provereno_v' => $token->proverenoV,
            'deleted' => $token->deleted ? 1 : 0,
        ]);
    }

    public function update(LichniyTokenList $token): void
    {
        $this->connection->update(self::TABLE, [
            'nazvanie' => $token->nazvanie,
            'token_cipher' => $token->tokenCipher,
            'token_hint' => $token->tokenHint,
            'obyom_prav' => $token->obyomPrav,
            'proekty_s_pushom' => json_encode($token->proektySPushom, JSON_THROW_ON_ERROR),
            'provereno_v' => $token->proverenoV,
            'deleted' => $token->deleted ? 1 : 0,
        ], ['id' => $token->id]);
    }

    public function markDeleted(string $id): void
    {
        $this->connection->update(self::TABLE, ['deleted' => 1], ['id' => $id]);
    }

    public function getById(string $id): LichniyTokenList
    {
        $token = $this->findById($id);

        if ($token === null) {
            throw new \DomainException('lichniy_token.ne_nayden');
        }

        return $token;
    }

    public function findById(string $id): ?LichniyTokenList
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE id = :id',
            ['id' => $id],
        );

        return empty($row) ? null : $this->makeToken($row);
    }

    /**
     * @return LichniyTokenList[]
     */
    public function findByAccountId(string $accountId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE account_id = :account_id ORDER BY provereno_v DESC',
            ['account_id' => $accountId],
        );

        return array_map(fn (array $row): LichniyTokenList => $this->makeToken($row), $rows);
    }

    /**
     * @return LichniyTokenList[]
     */
    public function findByAccountIdAndRepoId(string $accountId, string $repoId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE account_id = :account_id AND repo_id = :repo_id',
            ['account_id' => $accountId, 'repo_id' => $repoId],
        );

        return array_map(fn (array $row): LichniyTokenList => $this->makeToken($row), $rows);
    }

    /**
     * @return LichniyTokenList[]
     */
    public function findAll(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM ' . self::TABLE . ' ORDER BY provereno_v DESC',
        );

        return array_map(fn (array $row): LichniyTokenList => $this->makeToken($row), $rows);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function makeToken(array $row): LichniyTokenList
    {
        $proekty = json_decode((string) ($row['proekty_s_pushom'] ?? '[]'), true);
        $deleted = $row['deleted'];

        return new LichniyTokenList(
            (string) $row['id'],
            (string) $row['account_id'],
            (string) $row['repo_id'],
            (string) $row['nazvanie'],
            (string) $row['token_cipher'],
            (string) $row['token_hint'],
            (string) $row['obyom_prav'],
            is_array($proekty) ? array_values(array_map('strval', $proekty)) : [],
            (int) $row['provereno_v'],
            $deleted === true || (int) $deleted === 1,
        );
    }
}
