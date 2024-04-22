<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\ZapolnitPeremenieUnita;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;

final class ZapolnitPeremenieUnitaUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private UnitmanSecurityService $securityService
    )
    {
    }

    function handle(ZapolnitPeremenieUnita $command): void
    {
        $unit = $this->unitRepository->getById($command->id);
        $unit->proverkaPrav($this->securityService->getCurrentUserId());
        $unit->zapolnitPeremenie($command->values);
        $this->unitRepository->save($unit);
    }
}
