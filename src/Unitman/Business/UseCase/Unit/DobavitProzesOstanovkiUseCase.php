<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Port\CanGenerateGuid;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\Utils\ProverkaSvobodyUnita;

final class DobavitProzesOstanovkiUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private UnitmanSecurityService $securityService,
        private CanGenerateGuid         $uuidGenerator,
        private ProverkaSvobodyUnita    $proverkaSvobody,
    )
    {
    }

    function handle(string $unitId): void
    {
        $userId = $this->securityService->getCurrentUserId();
        $prozesId = $this->uuidGenerator->makeGuid();
        $unit = $this->unitRepository->getById($unitId);
        $this->proverkaSvobody->proverit($unit);
        $unit->dobavitProzesOstanovki($userId, $prozesId);
        $this->unitRepository->save($unit);
    }
}
