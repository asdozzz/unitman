<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\SbrositPodgotovkuUnita;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\Port\UnitRepository;

final class SbrositPodgotovkuUnitaUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private RunnerService $runnerService,
        private UnitmanSecurityService $securityService
    )
    {
    }

    function handle(SbrositPodgotovkuUnita $command): void
    {
        $unit = $this->unitRepository->getById($command->id);
        if (!$unit->esliRazreshenoUpravlyatUnitom($this->securityService->getCurrentUserId())) {
            throw new \DomainException('unit.ne_hvataet_prav');
        }
        $jobId = $this->runnerService->nachatSbrosPodgotovkiUnita($unit);
        $unit->nachatSbrosPodgotovkiUnita($jobId);
        $this->unitRepository->save($unit);
    }
}
