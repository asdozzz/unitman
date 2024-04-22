<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\PoluchitKonfigUnita;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;

final class PoluchitKonfigUnitaQuery
{
    public function __construct(
        private UnitRepository $unitRepository,
        private UnitmanSecurityService $securityService
    )
    {
    }

    function handle(PoluchitKonfigUnita $command): array
    {
        $unit = $this->unitRepository->getById($command->id);
        $unit->proverkaPrav($this->securityService->getCurrentUserId());
        $config = $unit->getConfig();

        if (empty($config)) {
            return [];
        }
        //TODO реализовать ReadModel для конфига
        return $config->toArray();
    }
}
