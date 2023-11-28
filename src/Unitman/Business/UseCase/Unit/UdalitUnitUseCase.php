<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\ObnovitKodUnita;
use App\Unitman\Business\Command\Unit\UdalitUnit;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\Port\UnitRepository;

final class UdalitUnitUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private RunnerService $runnerService,
        private UnitmanSecurityService $securityService
    )
    {
    }

    function handle(UdalitUnit $command): void
    {
        $unit = $this->unitRepository->getById($command->id);
        if (!$unit->esliRazreshenoUpravlyatUnitom($this->securityService->getCurrentUserId())) {
            throw new \DomainException('unit.ne_hvataet_prav');
        }
        $jobId = $this->runnerService->nachatUdalenieUnita($unit);
        $unit->nachatUdalenieUnita($jobId);
        $this->unitRepository->save($unit);
    }
}
