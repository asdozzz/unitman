<?php

namespace App\Utils\EventSauce\Repository;

use App\Account\Business\Utils\AccountEventTypeEnum;
use App\Utils\EventSauce\Model\StreamName;
use Doctrine\DBAL\Connection;
use EventSauce\EventSourcing\AggregateRoot;
use EventSauce\EventSourcing\AggregateRootId;
use EventSauce\EventSourcing\ClassNameInflector;
use EventSauce\EventSourcing\Header;
use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageDecorator;
use EventSauce\EventSourcing\MessageRepository;
use EventSauce\EventSourcing\Serialization\MessageSerializer;
use EventSauce\EventSourcing\UnableToReconstituteAggregateRoot;
use EventSauce\EventSourcing\UnableToRetrieveMessages;

use Generator;

final class DoctrineStreamRepository
{
    public function __construct(
        private Connection $connection,
        private string $tableName,
        private MessageSerializer $serializer,
        private ClassNameInflector $classNameInflector,
        private MessageDecorator $decorator,
        private MessageRepository $messageRepository
    )
    {

    }

    /**
     * @param class-string $aggregateClass
     * */
    public function getEventStreamByAggregateName(string $aggregateClass): StreamName
    {
        $typeName = $this->classNameInflector->classNameToType($aggregateClass);
        return new StreamName($typeName);
    }

    /**
     * @template T of AggregateRoot
     * @param class-string<T> $aggregateClass
     *
     * @return T
     * */
    public function retrieve(string $aggregateClass, AggregateRootId $aggregateRootId)
    {
        try {
            $events = $this->retrieveAllEvents($aggregateRootId);
            /** @psalm-suppress InvalidArgument*/
            return $aggregateClass::reconstituteFromEvents($aggregateRootId, $events);
        } catch (\Throwable $throwable) {
            throw UnableToReconstituteAggregateRoot::becauseOf($throwable->getMessage(), $throwable);
        }
    }

    private function retrieveAllEvents(AggregateRootId $aggregateRootId): Generator
    {
        $messages = $this->messageRepository->retrieveAll($aggregateRootId);
        /** @var Generator<Message> $messages */

        foreach ($messages as $message) {
            yield $message->payload();
        }

        return $messages->getReturn();
    }

    public function persist(AggregateRoot $aggregateRoot): void
    {
        $events = $aggregateRoot->releaseEvents();

        if (count($events) === 0) {
            return;
        }

        $aggregateRootVersion = $aggregateRoot->aggregateRootVersion();
        $aggregateRootId = $aggregateRoot->aggregateRootId();

        // decrease the aggregate root version by the number of raised events
        // so the version of each message represents the version at the time
        // of recording.
        $aggregateRootVersion = $aggregateRootVersion - count($events);
        $metadata = [
            Header::AGGREGATE_ROOT_ID => $aggregateRootId,
            Header::AGGREGATE_ROOT_TYPE => $this->classNameInflector->classNameToType($aggregateRoot::class),
        ];
        $messages = array_map(function (object $event) use ($metadata, &$aggregateRootVersion) {
            return $this->decorator->decorate(new Message(
                $event,
                $metadata + [Header::AGGREGATE_ROOT_VERSION => ++$aggregateRootVersion]
            ));
        }, $events);

        $this->messageRepository->persist(...$messages);
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

    function getStream(StreamName $streamName, int $checkpoint, int $limit = 0): \Generator
    {
        $aggregateType = $streamName->aggregateType;

        $where = '';
        if (!empty($streamName->eventTypes)) {
            $where = "AND payload->'headers'->>'__event_type' in ('".join("','", $streamName->eventTypes)."')";
        }

        $limitStr = '';
        if (!empty($limit)) {
            $limitStr = 'LIMIT '.$limit;
        }


        $sql = "SELECT payload FROM $this->tableName WHERE payload->'headers'->>'__aggregate_root_type' = '$aggregateType' $where ORDER BY id asc $limitStr OFFSET $checkpoint";
        $rows = $this->connection
            ->fetchAllAssociative($sql);
        try {
            return $this->yieldMessagesFromPayloads(array_map(fn(array $row): string => $row['payload'],$rows));
        } catch (\Throwable $exception) {
            throw UnableToRetrieveMessages::dueTo('', $exception);
        }
    }
}
