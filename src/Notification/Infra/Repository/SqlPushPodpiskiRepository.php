<?php

declare(strict_types=1);

namespace App\Notification\Infra\Repository;

use App\Notification\Business\Model\PushPodpiska;
use App\Notification\Business\Port\PushPodpiskiRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final class SqlPushPodpiskiRepository implements PushPodpiskiRepository
{
    private const TABLE = 'notification_push_subscription';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function save(PushPodpiska $podpiska): void
    {
        $table = self::TABLE;
        $this->connection->executeStatement(
            "insert into $table
                (id, account_id, endpoint, p256dh, auth, locale, created_at, updated_at)
             values
                (:id, :account_id, :endpoint, :p256dh, :auth, :locale, :created_at, :updated_at)
             on conflict (endpoint)
             do update set
                account_id = excluded.account_id,
                p256dh = excluded.p256dh,
                auth = excluded.auth,
                locale = excluded.locale,
                updated_at = excluded.updated_at",
            [
                'id' => $podpiska->getId(),
                'account_id' => $podpiska->getAccountId(),
                'endpoint' => $podpiska->getEndpoint(),
                'p256dh' => $podpiska->getP256dh(),
                'auth' => $podpiska->getAuth(),
                'locale' => $podpiska->getLocale(),
                'created_at' => $podpiska->getCreatedAt(),
                'updated_at' => $podpiska->getUpdatedAt(),
            ],
            [
                'id' => ParameterType::STRING,
                'account_id' => ParameterType::STRING,
                'endpoint' => ParameterType::STRING,
                'p256dh' => ParameterType::STRING,
                'auth' => ParameterType::STRING,
                'locale' => ParameterType::STRING,
                'created_at' => ParameterType::INTEGER,
                'updated_at' => ParameterType::INTEGER,
            ],
        );
    }

    public function findByEndpoint(string $endpoint): ?PushPodpiska
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative(
            "select * from $table where endpoint = :endpoint",
            ['endpoint' => $endpoint],
        );

        return $row === false ? null : self::izStroki($row);
    }

    public function deleteByEndpoint(string $accountId, string $endpoint): void
    {
        $table = self::TABLE;
        $this->connection->executeStatement(
            "delete from $table where account_id = :account_id and endpoint = :endpoint",
            ['account_id' => $accountId, 'endpoint' => $endpoint],
            ['account_id' => ParameterType::STRING, 'endpoint' => ParameterType::STRING],
        );
    }

    public function deleteById(string $id): void
    {
        $table = self::TABLE;
        $this->connection->executeStatement(
            "delete from $table where id = :id",
            ['id' => $id],
            ['id' => ParameterType::STRING],
        );
    }

    public function findDlyaAccounts(array $accountIds): array
    {
        if ($accountIds === []) {
            return [];
        }

        $table = self::TABLE;
        $rows = $this->connection->fetchAllAssociative(
            "select * from $table where account_id in (:account_ids) order by account_id, id",
            ['account_ids' => $accountIds],
            ['account_ids' => ArrayParameterType::STRING],
        );

        return array_map(
            static fn (array $row): PushPodpiska => self::izStroki($row),
            $rows,
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function izStroki(array $row): PushPodpiska
    {
        return new PushPodpiska(
            (string) $row['id'],
            (string) $row['account_id'],
            (string) $row['endpoint'],
            (string) $row['p256dh'],
            (string) $row['auth'],
            (string) $row['locale'],
            (int) $row['created_at'],
            (int) $row['updated_at'],
        );
    }
}
