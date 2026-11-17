<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\VipolnitDeistviye;
use App\Unitman\Business\Port\CanGenerateGuid;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\Utils\ProverkaSvobodyUnita;

final class DobavitProzesVipolneniyaDeistviyaUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private UnitmanSecurityService $securityService,
        private CanGenerateGuid         $uuidGenerator,
        private ProverkaSvobodyUnita    $proverkaSvobody,
    )
    {
    }

    function handle(VipolnitDeistviye $command): void
    {
        $userId = $this->securityService->getCurrentUserId();
        $prozesId = $this->uuidGenerator->makeGuid();
        $unit = $this->unitRepository->getById($command->id);
        $this->proverkaSvobody->proverit($unit);
        $unit->dobavitProzesVipolneniyaDeistviya($userId, $prozesId, $command);
        $this->unitRepository->save($unit);
    }
}
