<?php

namespace App\Utils\EventSauce\Repository;

use App\Account\Business\Utils\AccountEventTypeEnum;
use App\Utils\EventSauce\Model\StreamName;
use Doctrine\DBAL\Connection;
use EventSauce\EventSourcing\Header;
use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\Serialization\MessageSerializer;
use EventSauce\EventSourcing\UnableToRetrieveMessages;

use Generator;

final class DoctrineStreamRepository
{
    public function __construct(
        private Connection $connection,
        private string $tableName,
        private MessageSerializer $serializer
    )
    {

    }

    /**
     * @psalm-return Generator<Message>
     */
    private function yieldMessagesFromPayloads(iterable $payloads): Generator
    {
        foreach ($payloads as $payload) {
            yield $message = $this->serializer->unserializePayload(json_decode($payload, true));
        }

        return isset($message)
            ? $message->header(Header::AGGREGATE_ROOT_VERSION) ?: 0
            : 0;
    }

    function getStream(StreamName $streamName, int $checkpoint): \Generator
    {
        $aggregateType = $streamName->aggregateType;

        $where = '';
        if (!empty($streamName->eventTypes)) {
            $where = "AND payload->'headers'->>'__event_type' in ('".join("','", $streamName->eventTypes)."')";
        }

        $sql = "SELECT payload FROM $this->tableName WHERE payload->'headers'->>'__aggregate_root_type' = '$aggregateType' $where ORDER BY id asc OFFSET $checkpoint";
        $rows = $this->connection
            ->fetchAllAssociative($sql);
        try {
            return $this->yieldMessagesFromPayloads(array_map(fn(array $row)=> $row['payload'],$rows));
        } catch (\Throwable $exception) {
            throw UnableToRetrieveMessages::dueTo('', $exception);
        }
    }
}
