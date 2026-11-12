<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Port\CanGenerateGuid;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;

final class DobavitProzesPodgotovkiUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private UnitmanSecurityService $securityService,
        private CanGenerateGuid         $uuidGenerator,
    )
    {
    }

    function handle(string $unitId): void
    {
        $userId = $this->securityService->getCurrentUserId();
        $prozesId = $this->uuidGenerator->makeGuid();
        $unit = $this->unitRepository->getById($unitId);
        $unit->dobavitProzesPodgotovki($userId, $prozesId);
        $this->unitRepository->save($unit);
    }
}
