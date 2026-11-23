<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Model\SobitieIzHranilisha\TipSobitiya;
use App\Unitman\Business\Port\CanGenerateGuid;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Unit\OtlozhennieDeistviyaRepository;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\Utils\ProverkaSvobodyUnita;

final class DobavitProzesObnovleniyaUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private UnitmanSecurityService $securityService,
        private CanGenerateGuid         $uuidGenerator,
        private ProjectRepository $projectRepository,
        private ProverkaSvobodyUnita $proverkaSvobody,
        private OtlozhennieDeistviyaRepository $otlozhennieRepository,
    )
    {
    }

    function handle(string $unitId): void
    {
        $userId = $this->securityService->getCurrentUserId();
        $prozesId = $this->uuidGenerator->makeGuid();
        $unit = $this->unitRepository->getById($unitId);
        $this->proverkaSvobody->proverit($unit);
        $project = $this->projectRepository->getById($unit->getProjectId());
        $unit->dobavitProzesObnovleniya($userId, $prozesId, false, $project->poluchitNastroikiHuka()->obnovlenieBezSbrosaPodgotovki);
        $this->unitRepository->save($unit);
        $this->otlozhennieRepository->udalitPoUnituITipu($unitId, TipSobitiya::KOD_OBNOVLEN);
    }

    /**
     * Системный сценарий: вебхук и применение отложенных намерений.
     *
     * Гейт здесь не ломает отложенное применение: `PrimenitOtlozhennieDeistviya`
     * `DlyaUnitaUseCase` проверяет свободу сам и трактует
     * `unit.zanyat_hodom_opencode` как повод отложить намерение ещё раз.
     * Проверка внутри метода закрывает гонку между той проверкой и записью.
     */
    function handleSystem(string $unitId): void
    {
        $systemUser = $this->securityService->getSystemUser();
        $prozesId = $this->uuidGenerator->makeGuid();
        $unit = $this->unitRepository->getById($unitId);
        $this->proverkaSvobody->proverit($unit);
        $project = $this->projectRepository->getById($unit->getProjectId());
        $unit->dobavitProzesObnovleniya($systemUser->id, $prozesId, true, $project->poluchitNastroikiHuka()->obnovlenieBezSbrosaPodgotovki);
        $this->unitRepository->save($unit);
        $this->otlozhennieRepository->udalitPoUnituITipu($unitId, TipSobitiya::KOD_OBNOVLEN);
    }
}
