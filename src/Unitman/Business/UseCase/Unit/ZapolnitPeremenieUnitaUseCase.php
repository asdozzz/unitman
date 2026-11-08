<?php

declare(strict_types=1);

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\ZapolnitPeremenieUnita;
use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Business\Port\MediatekaService;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;

final class ZapolnitPeremenieUnitaUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private ProjectRepository $projectRepository,
        private UnitmanSecurityService $securityService,
        private CanGeneateGuid $canGeneateGuid,
        private MediatekaService $mediatekaService,
        private ProverkaFaylovVolumov $proverkaFaylovVolumov,
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

        // Файлы томов: черновики прикрепляются к юниту, архивы каталога проекта
        // используются повторно, файлы этого юнита разрешены без перепривязки.
        // Наличие `dir` в конфиге валидирует сам агрегат.
        $chernoviki = $this->proverkaFaylovVolumov->proverit($unit->getProjectId(), $command->volumes, $unit->getId());

        $unit->zapolnitPeremenie($command->values, $command->volumes);
        if ($unit->esliZapushen() || $unit->esliPodgotovlen()) {
            $prozesId = $this->canGeneateGuid->makeGuid();
            $unit->dobavitProzesObnovleniya($userId, $prozesId, true, $project->poluchitNastroikiHuka()->obnovlenieBezSbrosaPodgotovki);
        }
        $this->unitRepository->save($unit);

        if ($chernoviki !== []) {
            $this->mediatekaService->prikrepitFaylyKUnitu($chernoviki, $unit->getId());
        }
    }
}
