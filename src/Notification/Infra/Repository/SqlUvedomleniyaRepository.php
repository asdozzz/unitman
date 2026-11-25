<?php

declare(strict_types=1);

namespace App\Notification\Infra\Repository;

use App\Notification\Business\Model\TipUvedomleniya;
use App\Notification\Business\Model\UrovenVazhnosti;
use App\Notification\Business\Model\Uvedomlenie;
use App\Notification\Business\Port\UvedomleniyaRepository;
use App\Notification\Business\ReadModel\UvedomlenieReadModel;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final class SqlUvedomleniyaRepository implements UvedomleniyaRepository
{
    private const NOTIFICATION = 'notification';
    private const RECIPIENT = 'notification_recipient';
    private const DELIVERY = 'notification_delivery';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function save(Uvedomlenie $uvedomlenie, array $recipientIds, string $channel): void
    {
        $this->connection->transactional(function () use ($uvedomlenie, $recipientIds, $channel): void {
            $notification = self::NOTIFICATION;
            $affected = $this->connection->executeStatement(
                "insert into $notification
                    (id, type, severity, requires_read, payload, dedup_key, created_at)
                 values
                    (:id, :type, :severity, :requires_read, :payload, :dedup_key, :created_at)
                 on conflict do nothing",
                [
                    'id' => $uvedomlenie->getId(),
                    'type' => $uvedomlenie->getType()->value,
                    'severity' => $uvedomlenie->getSeverity()->value,
                    'requires_read' => $uvedomlenie->requiresRead(),
                    'payload' => json_encode($uvedomlenie->getPayload(), JSON_THROW_ON_ERROR),
                    'dedup_key' => $uvedomlenie->getDedupKey(),
                    'created_at' => $uvedomlenie->getCreatedAt(),
                ],
                [
                    'id' => ParameterType::STRING,
                    'type' => ParameterType::STRING,
                    'severity' => ParameterType::STRING,
                    'requires_read' => ParameterType::BOOLEAN,
                    'payload' => ParameterType::STRING,
                    'dedup_key' => ParameterType::STRING,
                    'created_at' => ParameterType::INTEGER,
                ],
            );

            if ($affected === 0) {
                return;
            }

            $recipientTable = self::RECIPIENT;

            foreach ($recipientIds as $accountId) {
                $this->connection->executeStatement(
                    "insert into $recipientTable (notification_id, account_id, read_at, created_at)
                     values (:notification_id, :account_id, null, :created_at)
                     on conflict do nothing",
                    [
                        'notification_id' => $uvedomlenie->getId(),
                        'account_id' => $accountId,
                        'created_at' => $uvedomlenie->getCreatedAt(),
                    ],
                    [
                        'notification_id' => ParameterType::STRING,
                        'account_id' => ParameterType::STRING,
                        'created_at' => ParameterType::INTEGER,
                    ],
                );
            }

            $delivery = self::DELIVERY;
            $this->connection->executeStatement(
                "insert into $delivery
                    (notification_id, channel, state, attempts, dispatched_at, sent_at, last_error)
                 values (:notification_id, :channel, 'pending', 0, null, null, null)
                 on conflict do nothing",
                ['notification_id' => $uvedomlenie->getId(), 'channel' => $channel],
                ['notification_id' => ParameterType::STRING, 'channel' => ParameterType::STRING],
            );
        });
    }

    public function findByDedupKey(string $dedupKey): ?Uvedomlenie
    {
        $table = self::NOTIFICATION;
        $row = $this->connection->fetchAssociative(
            "select * from $table where dedup_key = :dedup_key limit 1",
            ['dedup_key' => $dedupKey],
        );

        return $row === false ? null : self::izStroki($row);
    }

    public function findById(string $id): ?Uvedomlenie
    {
        $table = self::NOTIFICATION;
        $row = $this->connection->fetchAssociative(
            "select * from $table where id = :id",
            ['id' => $id],
        );

        return $row === false ? null : self::izStroki($row);
    }

    public function findDlyaPolzovatelya(string $accountId, int $limit, bool $onlyActionable): array
    {
        $notification = self::NOTIFICATION;
        $recipient = self::RECIPIENT;
        $rows = $this->connection->fetchAllAssociative(
            "select n.id, n.type, n.severity, n.requires_read, n.payload, n.created_at, r.read_at
             from $notification n
             join $recipient r on r.notification_id = n.id
             where r.account_id = :account_id
               and (:only_actionable = false or n.requires_read = true)
             order by n.created_at desc, n.id desc
             limit :limit",
            ['account_id' => $accountId, 'only_actionable' => $onlyActionable, 'limit' => $limit],
            [
                'account_id' => ParameterType::STRING,
                'only_actionable' => ParameterType::BOOLEAN,
                'limit' => ParameterType::INTEGER,
            ],
        );

        return array_map(
            static fn (array $row): UvedomlenieReadModel => self::izStrokiDlyaPolzovatelya($row),
            $rows,
        );
    }

    public function schetchikNeprochitannih(string $accountId): int
    {
        $notification = self::NOTIFICATION;
        $recipient = self::RECIPIENT;

        return (int) $this->connection->fetchOne(
            "select count(*)
             from $notification n
             join $recipient r on r.notification_id = n.id
             where r.account_id = :account_id
               and n.requires_read = true
               and r.read_at is null",
            ['account_id' => $accountId],
        );
    }

    public function estPoluchatelem(string $notificationId, string $accountId): bool
    {
        $recipient = self::RECIPIENT;

        return $this->connection->fetchOne(
            "select 1 from $recipient where notification_id = :notification_id and account_id = :account_id",
            ['notification_id' => $notificationId, 'account_id' => $accountId],
        ) !== false;
    }

    public function otmetitProchitannim(string $notificationId, string $accountId, int $now): bool
    {
        $recipient = self::RECIPIENT;
        $notification = self::NOTIFICATION;
        $affected = $this->connection->executeStatement(
            "update $recipient r
             set read_at = :now
             from $notification n
             where n.id = r.notification_id
               and r.notification_id = :notification_id
               and r.account_id = :account_id
               and n.requires_read = true
               and r.read_at is null",
            [
                'now' => $now,
                'notification_id' => $notificationId,
                'account_id' => $accountId,
            ],
            [
                'now' => ParameterType::INTEGER,
                'notification_id' => ParameterType::STRING,
                'account_id' => ParameterType::STRING,
            ],
        );

        return $affected > 0;
    }

    public function otmetitVseProchitannimi(string $accountId, int $now): int
    {
        $recipient = self::RECIPIENT;
        $notification = self::NOTIFICATION;

        return (int) $this->connection->executeStatement(
            "update $recipient r
             set read_at = :now
             from $notification n
             where n.id = r.notification_id
               and r.account_id = :account_id
               and n.requires_read = true
               and r.read_at is null",
            ['now' => $now, 'account_id' => $accountId],
            ['now' => ParameterType::INTEGER, 'account_id' => ParameterType::STRING],
        );
    }

    public function udalitStarie(bool $requiresRead, int $createdBefore): int
    {
        $table = self::NOTIFICATION;

        return (int) $this->connection->executeStatement(
            "delete from $table where requires_read = :requires_read and created_at < :created_before",
            ['requires_read' => $requiresRead, 'created_before' => $createdBefore],
            ['requires_read' => ParameterType::BOOLEAN, 'created_before' => ParameterType::INTEGER],
        );
    }

    public function findPoluchateleyIds(string $notificationId): array
    {
        $recipient = self::RECIPIENT;
        $rows = $this->connection->fetchFirstColumn(
            "select account_id from $recipient where notification_id = :notification_id",
            ['notification_id' => $notificationId],
        );

        return array_map(static fn (mixed $id): string => (string) $id, $rows);
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function izStroki(array $row): Uvedomlenie
    {
        return new Uvedomlenie(
            (string) $row['id'],
            TipUvedomleniya::from((string) $row['type']),
            UrovenVazhnosti::from((string) $row['severity']),
            (bool) $row['requires_read'],
            self::payload($row['payload'] ?? null),
            isset($row['dedup_key']) ? (string) $row['dedup_key'] : null,
            (int) $row['created_at'],
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function izStrokiDlyaPolzovatelya(array $row): UvedomlenieReadModel
    {
        return new UvedomlenieReadModel(
            (string) $row['id'],
            TipUvedomleniya::from((string) $row['type']),
            UrovenVazhnosti::from((string) $row['severity']),
            (bool) $row['requires_read'],
            self::payload($row['payload'] ?? null),
            (int) $row['created_at'],
            isset($row['read_at']) ? (int) $row['read_at'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function payload(mixed $raw): array
    {
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }
}
