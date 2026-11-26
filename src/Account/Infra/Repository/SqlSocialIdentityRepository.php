<?php

declare(strict_types=1);

namespace App\Account\Infra\Repository;

use App\Account\Business\Model\OAuth\SocialIdentity;
use App\Account\Business\Port\SocialIdentityRepository;
use Doctrine\DBAL\Connection;

final class SqlSocialIdentityRepository implements SocialIdentityRepository
{
    private const TABLE = 'account_social_identity';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function findByProviderAndUserId(string $provider, string $providerUserId): ?SocialIdentity
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative(
            "SELECT * FROM $table WHERE provider = :provider AND provider_user_id = :providerUserId",
            ['provider' => $provider, 'providerUserId' => $providerUserId],
        );

        return $row === false ? null : $this->makeIdentity($row);
    }

    public function findByAccountIdAndProvider(string $accountId, string $provider): ?SocialIdentity
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative(
            "SELECT * FROM $table WHERE account_id = :accountId AND provider = :provider",
            ['accountId' => $accountId, 'provider' => $provider],
        );

        return $row === false ? null : $this->makeIdentity($row);
    }

    public function save(SocialIdentity $identity): void
    {
        $table = self::TABLE;

        $this->connection->insert($table, [
            'id' => $identity->id,
            'account_id' => $identity->accountId,
            'provider' => $identity->provider,
            'provider_user_id' => $identity->providerUserId,
            'email' => $identity->email,
            'linked_at' => gmdate('Y-m-d H:i:sP', $identity->linkedAt),
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function makeIdentity(array $row): SocialIdentity
    {
        return new SocialIdentity(
            (string) $row['id'],
            (string) $row['account_id'],
            (string) $row['provider'],
            (string) $row['provider_user_id'],
            (string) $row['email'],
            (int) strtotime((string) $row['linked_at']),
        );
    }
}
