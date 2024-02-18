<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\UdalitSlomaniyUnit;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;

final class UdalitSlomaniyUnitUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private UnitmanSecurityService $securityService
    )
    {
    }

    function handle(UdalitSlomaniyUnit $command): void
    {
        $unit = $this->unitRepository->getById($command->id);
        if (!$unit->esliRazreshenoUpravlyatUnitom($this->securityService->getCurrentUserId())) {
            throw new \DomainException('unit.ne_hvataet_prav');
        }
        $unit->udalitSlomaniyUnit();
        $this->unitRepository->save($unit);
    }
}
