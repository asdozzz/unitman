<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;

final class DobavitProzesObnovleniyaUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private UnitmanSecurityService $securityService,
        private CanGeneateGuid         $uuidGenerator,
        private ProjectRepository $projectRepository
    )
    {
    }

    function handle(string $unitId): void
    {
        $userId = $this->securityService->getCurrentUserId();
        $prozesId = $this->uuidGenerator->makeGuid();
        $unit = $this->unitRepository->getById($unitId);
        $project = $this->projectRepository->getById($unit->getProjectId());
        $unit->dobavitProzesObnovleniya($userId, $prozesId, false, $project->poluchitNastroikiHuka()->obnovlenieBezSbrosaPodgotovki);
        $this->unitRepository->save($unit);
    }

    function handleSystem(string $unitId): void
    {
        $systemUser = $this->securityService->getSystemUser();
        $prozesId = $this->uuidGenerator->makeGuid();
        $unit = $this->unitRepository->getById($unitId);
        $project = $this->projectRepository->getById($unit->getProjectId());
        $unit->dobavitProzesObnovleniya($systemUser->id, $prozesId, true, $project->poluchitNastroikiHuka()->obnovlenieBezSbrosaPodgotovki);
        $this->unitRepository->save($unit);
    }
}
