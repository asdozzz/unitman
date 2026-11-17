<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Port\CanGenerateGuid;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\Utils\ProverkaSvobodyUnita;

final class DobavitProzesUdaleniyaUseCase
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
        $unit->dobavitProzesUdaleniya($userId, $prozesId);
        $this->unitRepository->save($unit);
    }

    /**
     * Системный сценарий: вебхук, уборка неактивных юнитов и применение
     * отложенных намерений. Отложенное применение не ломается: вызывающий
     * `PrimenitOtlozhennieDeistviyaDlyaUnitaUseCase` проверяет свободу сам и
     * трактует `unit.zanyat_hodom_opencode` как повод отложить намерение.
     * Проверка внутри метода закрывает гонку до записи.
     */
    function handleSystem(string $unitId): void
    {
        $systemUser = $this->securityService->getSystemUser();
        $prozesId = $this->uuidGenerator->makeGuid();
        $unit = $this->unitRepository->getById($unitId);
        $this->proverkaSvobody->proverit($unit);
        $unit->dobavitProzesUdaleniya($systemUser->id, $prozesId, true);
        $this->unitRepository->save($unit);
    }
}
