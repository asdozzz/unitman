<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;

final class DobavitProzesUdaleniyaUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private UnitmanSecurityService $securityService,
        private CanGeneateGuid         $uuidGenerator,
    )
    {
    }

    function handle(string $unitId): void
    {
        $userId = $this->securityService->getCurrentUserId();
        $prozesId = $this->uuidGenerator->makeGuid();
        $unit = $this->unitRepository->getById($unitId);
        $unit->dobavitProzesUdaleniya($userId, $prozesId);
        $this->unitRepository->save($unit);
    }

    function handleSystem(string $unitId): void
    {
        $systemUser = $this->securityService->getSystemUser();
        $prozesId = $this->uuidGenerator->makeGuid();
        $unit = $this->unitRepository->getById($unitId);
        $unit->dobavitProzesUdaleniya($systemUser->id, $prozesId);
        $this->unitRepository->save($unit);
    }
}
