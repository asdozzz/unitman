<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\ObnovitKodUnitaPosleZapuska;
use App\Unitman\Business\Command\Unit\ZapolnitPeremenieUnita;
use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;

final class ZapolnitPeremenieUnitaUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private ProjectRepository $projectRepository,
        private UnitmanSecurityService $securityService,
        private CanGeneateGuid $canGeneateGuid
    )
    {
    }

    function handle(ZapolnitPeremenieUnita $command): void
    {
        $unit = $this->unitRepository->getById($command->id);
        $project = $this->projectRepository->getById($unit->getProjectId());
        $userId = $this->securityService->getCurrentUserId();
        $projectUser = $project->getProjectUserById($userId);
        $unit->proverkaPrav($projectUser);
        $unit->zapolnitPeremenie($command->values);
        if ($unit->esliZapushen() || $unit->esliPodgotovlen()) {
            $prozesId = $this->canGeneateGuid->makeGuid();
            $unit->dobavitProzesObnovleniya($userId, $prozesId, true, $project->poluchitNastroikiHuka()->obnovlenieBezSbrosaPodgotovki);
        }
        $this->unitRepository->save($unit);
    }
}
