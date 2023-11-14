<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\ObnovitKodUnita;
use App\Unitman\Business\Command\Unit\ZapustitUnit;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\Port\UnitRepository;
use Symfony\Bundle\SecurityBundle\Security;

final class ZapustitUnitUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private RunnerService $runnerService,
        private UnitmanSecurityService $securityService
    )
    {
    }

    function handle(ZapustitUnit $command): void
    {
        $unit = $this->unitRepository->getById($command->unitId);

        if (!$unit->esliRazreshenoUpravlyatUnitom($this->securityService->getCurrentUserId())) {
            throw new \DomainException('unit.ne_hvataet_prav');
        }

        $jobId = $this->runnerService->nachatZapuskUnita($unit);
        $unit->nachatZapuskUnita($jobId);
        $this->unitRepository->save($unit);
    }
}
