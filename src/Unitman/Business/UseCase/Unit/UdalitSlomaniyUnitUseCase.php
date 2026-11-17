<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\UdalitSlomaniyUnit;
use App\Unitman\Business\Port\MediatekaService;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\Utils\ProverkaSvobodyUnita;

final class UdalitSlomaniyUnitUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private ProjectRepository $projectRepository,
        private UnitmanSecurityService $securityService,
        private MediatekaService $mediatekaService,
        private ProverkaSvobodyUnita $proverkaSvobody,
    )
    {
    }

    function handle(UdalitSlomaniyUnit $command): void
    {
        $unit = $this->unitRepository->getById($command->id);
        $this->proverkaSvobody->proverit($unit);
        $project = $this->projectRepository->getById($unit->getProjectId());
        $projectUser = $project->getProjectUserById($this->securityService->getCurrentUserId());
        $unit->proverkaPrav($projectUser);
        $unit->udalitSlomaniyUnit();
        $this->unitRepository->save($unit);

        // Сломанный юнит удалён — его файлы в медиатеке больше не нужны.
        $this->mediatekaService->udaliFayliUnita($command->id);
    }
}
