<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Port\CanGenerateGuid;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;

final class DobavitProzesZapuskaUseCase
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
        $this->handleDlyaPolzovatelya($unitId, $this->securityService->getCurrentUserId());
    }

    /**
     * Тот же сценарий, но от имени явно переданного пользователя: нужно
     * интеграциям (сессии OpenCode), которые действуют не от текущего запроса.
     */
    function handleDlyaPolzovatelya(string $unitId, string $userId): void
    {
        $prozesId = $this->uuidGenerator->makeGuid();
        $unit = $this->unitRepository->getById($unitId);
        $unit->dobavitProzesZapuska($userId, $prozesId);
        $this->unitRepository->save($unit);
    }
}
