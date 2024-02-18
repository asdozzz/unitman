<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\SbrositPodgotovkuUnita;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;

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
        $userId = $this->securityService->getCurrentUserId();

        $errs = $unit->esliMognoSbrositPodgotvku($userId);
        if (!empty($errs)) {
            throw new \DomainException($errs[0]);
        }

        $jobId = $this->runnerService->nachatSbrosPodgotovkiUnita($unit);
        $unit->nachatSbrosPodgotovkiUnita($jobId, $userId);
        $this->unitRepository->save($unit);
    }
}
