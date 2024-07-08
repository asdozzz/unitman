<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\IzmenitVetkuUnita;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;

final class IzmenitVetkuUnitaUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private RunnerService $runnerService,
        private ProjectRepository $projectRepository,
        private UnitmanSecurityService $securityService
    )
    {
    }

    function handle(IzmenitVetkuUnita $command): void
    {
        $unit = $this->unitRepository->getById($command->id);
        $project = $this->projectRepository->getById($unit->getProjectId());
        $projectUser = $project->getProjectUserById($this->securityService->getCurrentUserId());
        $unit->validateNewBranch($projectUser, $command->newBranch);

        $jobId = $this->runnerService->nachatIzmenenieVetkiUnita($unit, $command->newBranch);
        $unit->nachatIzmenenieVetkiUnita($jobId, $projectUser, $command->newBranch);
        $this->unitRepository->save($unit);
    }
}
