<?php

namespace App\Unitman\Infra\Repository\Unit;

use App\Unitman\Business\Model\Unit;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Utils\EventSauce\ProjectionsManager;

final class SqlUnitEventsRepository implements UnitRepository
{
    public function __construct(
        private ProjectionsManager $projectionsManager
    )
    {}
    public function getById(string $id): Unit
    {
        $repo = $this->projectionsManager->retrieve(Unit::class, Unit\UnitId::fromString($id));
        /** @var Unit $repo*/
        if ($repo->aggregateRootVersion() === 0) {
            throw new \DomainException('unit.not_found');
        }
        return $repo;
    }

    public function save(Unit $unit): void
    {
        $this->projectionsManager->persistAndPullProjections($unit);
    }
}
