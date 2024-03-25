<?php

namespace App\Unitman\Infra\Repository\Unit;

use App\Unitman\Business\Model\Unit;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Utils\UnitmanClassNameMapEnum;
use App\Utils\EventSauce\Model\StreamName;
use App\Utils\EventSauce\ProjectionsManager;
use Doctrine\DBAL\Connection;
use EventSauce\EventSourcing\EventSourcedAggregateRootRepository;

final class SqlUnitEventsRepository implements UnitRepository
{
    public function __construct(
        private Connection $connection,
        private EventSourcedAggregateRootRepository $esRepository,
        private ProjectionsManager $projectionsManager
    )
    {}
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
        $this->connection->transactional(function () use ($unit){
            $this->esRepository->persist($unit);
            $this->projectionsManager->pullAllProjectionsByStreamName(new StreamName(UnitmanClassNameMapEnum::Unit->value));
        });
    }
}
