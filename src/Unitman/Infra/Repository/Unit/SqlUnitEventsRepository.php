<?php

namespace App\Unitman\Infra\Repository\Unit;

use App\Unitman\Business\Model\Unit;
use App\Unitman\Business\Port\UnitRepository;
use Doctrine\DBAL\Connection;
use EventSauce\EventSourcing\EventSourcedAggregateRootRepository;
use EventSauce\EventSourcing\MessageDecorator;
use EventSauce\EventSourcing\MessageDispatcher;
use EventSauce\EventSourcing\MessageRepository;

final class SqlUnitEventsRepository implements UnitRepository
{
    private Connection $connection;

    public function __construct(Connection $connection, MessageRepository $messageRepository, MessageDispatcher $messageDispatcher, MessageDecorator $messageDecorator)
    {
        $this->esRepository = new EventSourcedAggregateRootRepository(
            Unit::class,
            $messageRepository,
            $messageDispatcher,
            $messageDecorator
        );
        $this->connection = $connection;
    }
    public function getById(string $id): Unit
    {
        $repo = $this->esRepository->retrieve(Unit\UnitId::fromString($id));
        /** @var Unit $repo*/
        if ($repo->aggregateRootVersion() === 0) {
            throw new \DomainException('unit.not_found');
        }
        return $repo;
    }

    public function save(Unit $unit): void
    {
        $this->connection->transactional(fn() => $this->esRepository->persist($unit));
    }
}
