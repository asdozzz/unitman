<?php

declare(strict_types=1);

namespace App\Notification\Infra\Repository;

use App\Notification\Business\Model\Dostavka;
use App\Notification\Business\Model\StatusDostavki;
use App\Notification\Business\Port\DostavkiRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final class SqlDostavkiRepository implements DostavkiRepository
{
    private const TABLE = 'notification_delivery';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function zahvatitNaOtpravku(string $channel, int $limit, int $leaseSeconds, int $now): array
    {
        $table = self::TABLE;
        $leaseBefore = $now - $leaseSeconds;
        $rows = $this->connection->executeQuery(
            "update $table
             set state = 'dispatched', dispatched_at = :now
             where (channel, notification_id) in (
                 select channel, notification_id
                 from $table
                 where channel = :channel
                   and attempts < 3
                   and (
                       state = 'pending'
                       or (state = 'dispatched' and dispatched_at is not null and dispatched_at < :lease_before)
                   )
                 order by notification_id
                 limit :limit
                 for update skip locked
             )
             returning notification_id",
            [
                'now' => $now,
                'channel' => $channel,
                'lease_before' => $leaseBefore,
                'limit' => $limit,
            ],
            [
                'now' => ParameterType::INTEGER,
                'channel' => ParameterType::STRING,
                'lease_before' => ParameterType::INTEGER,
                'limit' => ParameterType::INTEGER,
            ],
        )->fetchFirstColumn();

        return array_map(static fn (mixed $id): string => (string) $id, $rows);
    }

    public function find(string $notificationId, string $channel): ?Dostavka
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative(
            "select * from $table where notification_id = :notification_id and channel = :channel",
            ['notification_id' => $notificationId, 'channel' => $channel],
        );

        return $row === false ? null : new Dostavka(
            (string) $row['notification_id'],
            (string) $row['channel'],
            StatusDostavki::from((string) $row['state']),
            (int) $row['attempts'],
            isset($row['dispatched_at']) ? (int) $row['dispatched_at'] : null,
            isset($row['sent_at']) ? (int) $row['sent_at'] : null,
            isset($row['last_error']) ? (string) $row['last_error'] : null,
        );
    }

    public function markSent(string $notificationId, string $channel, int $now): void
    {
        $table = self::TABLE;
        $this->connection->executeStatement(
            "update $table
             set state = 'sent', sent_at = :now, last_error = null
             where notification_id = :notification_id and channel = :channel",
            ['now' => $now, 'notification_id' => $notificationId, 'channel' => $channel],
            [
                'now' => ParameterType::INTEGER,
                'notification_id' => ParameterType::STRING,
                'channel' => ParameterType::STRING,
            ],
        );
    }

    public function markPending(string $notificationId, string $channel, string $error): void
    {
        $table = self::TABLE;
        $this->connection->executeStatement(
            "update $table
             set state = 'pending', dispatched_at = null, attempts = attempts + 1, last_error = :error
             where notification_id = :notification_id and channel = :channel",
            ['error' => $error, 'notification_id' => $notificationId, 'channel' => $channel],
            [
                'error' => ParameterType::STRING,
                'notification_id' => ParameterType::STRING,
                'channel' => ParameterType::STRING,
            ],
        );
    }

    public function markFailed(
        string $notificationId,
        string $channel,
        int $now,
        string $error,
        int $maxAttempts,
    ): void {
        $table = self::TABLE;
        $this->connection->executeStatement(
            "update $table
             set state = 'failed', attempts = least(attempts + 1, :max_attempts), last_error = :error
             where notification_id = :notification_id and channel = :channel",
            [
                'max_attempts' => $maxAttempts,
                'error' => $error,
                'notification_id' => $notificationId,
                'channel' => $channel,
            ],
            [
                'max_attempts' => ParameterType::INTEGER,
                'error' => ParameterType::STRING,
                'notification_id' => ParameterType::STRING,
                'channel' => ParameterType::STRING,
            ],
        );
    }
}
