<?php

namespace App\Unitman\Infra\Repository\Unit;

use App\Unitman\Business\Port\Unit\WebhookEvent\WebhookEventRepository;
use App\Unitman\Business\ReadModel\Unit\WebhookEvent;
use Doctrine\DBAL\Connection;
use Symfony\Component\Serializer\Serializer;

final class SqlWebhookEventRepository implements WebhookEventRepository
{
    const TABLE = 'webhook_unit';
    public function __construct(private Connection $connection, private Serializer $serializer)
    {
    }

    function init(): void
    {
        $table = self::TABLE;
        $this->connection->executeQuery("create table IF NOT EXISTS $table
            (
                id varchar not null primary key,
                webhook_id varchar not null,
                payload jsonb,
                sent_in_queue int,
                response jsonb,
                error varchar default null
            );
        ");
    }

    function destroy(): void
    {
        $table = self::TABLE;
        $this->connection->executeQuery("DROP TABLE IF EXISTS $table");
    }
    function truncate(): void
    {
        $table = self::TABLE;
        $this->connection->executeQuery("TRUNCATE $table");
    }

    function resetById(string $id): void
    {
        $table = self::TABLE;
        $this->connection->executeQuery("DELETE FROM $table where id = '$id'");
    }

    function insert(WebhookEvent $event): void
    {
        $data = [
            'id' => $event->id,
            'webhook_id' => $event->webhookId,
            'payload' => $this->serializer->serialize($event->payload, 'json'),
            'sent_in_queue' => $event->getSentInQueue(),
        ];

        $this->connection->insert(self::TABLE, $data);
    }

    function update(WebhookEvent $event): void
    {
        $data = [
            'sent_in_queue' => $event->getSentInQueue(),
            'response' => !empty($event->getResponse()) ? $this->serializer->serialize($event->getResponse(), 'json') : null,
            'error' => $event->getError()
        ];

        $this->connection->update(self::TABLE, $data, ['id' => $event->id]);
    }

    function getById(string $id): WebhookEvent
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative("SELECT * FROM $table WHERE id = :id", ['id' => $id]);
        if (empty($row)) {
            throw new \Exception('unit.webhook.not_found_by_id');
        }

        return $this->makeModelByRow($row);
    }

    /**
     * @param array $row
     * @return WebhookEvent
     */
    private function makeModelByRow(array $row): WebhookEvent
    {
        $payload = $this->serializer->deserialize($row['payload'], WebhookEvent\WebhookEventPayload::class, 'json');
        $response = null;
        if (!empty($row['response'])) {
            $response = $this->serializer->deserialize($row['response'], WebhookEvent\WebhookEventResponse::class, 'json');
        }

        $model = new WebhookEvent(
            $row['id'],
            $row['webhook_id'],
            $payload,
            $row['sent_in_queue'],
            $response,
            $row['error']
        );

        return $model;
    }

    /**
     * @return WebhookEvent[]
     * */
    function getEventsForSend(int $limit = 100): array
    {
        $table = self::TABLE;
        $rows = $this->connection->fetchAllAssociative("SELECT * FROM $table WHERE sent_in_queue = 0 
                    ORDER BY id asc LIMIT $limit for update skip locked");

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->makeModelByRow($row);
        }

        return $result;
    }

    function findAllByWebhookId(string $webhookId, int $limit = 10, int $offset = 0): array
    {
        $table = self::TABLE;
        $rows = $this->connection->fetchAllAssociative("SELECT * FROM $table 
            WHERE webhook_id = :webhookId and sent_in_queue = 1 ORDER BY id asc LIMIT :limit OFFSET :offset",
            ['webhookId' => $webhookId, 'limit' => $limit, 'offset' => $offset]);

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->makeModelByRow($row);
        }

        return $result;
    }


    function getCountByWebhookId(string $webhookId): int
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative("SELECT count(*) as cnt FROM $table 
            WHERE webhook_id = :webhookId",
            ['webhookId' => $webhookId]);

        return !empty($row['cnt']) ? $row['cnt'] : 0;
    }
}
