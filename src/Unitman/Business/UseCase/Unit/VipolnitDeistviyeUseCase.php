<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\VipolnitDeistviye;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;
use Symfony\Component\Clock\ClockInterface;

final class VipolnitDeistviyeUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private ProjectRepository $projectRepository,
        private UnitmanSecurityService $securityService,
        private RunnerService $runnerService,
        private ClockInterface $clock
    )
    {
    }
    function handle(VipolnitDeistviye $command): void
    {
        $unit = $this->unitRepository->getById($command->id);
        $project = $this->projectRepository->getById($unit->getProjectId());
        $projectUser = $project->getProjectUserById($this->securityService->getCurrentUserId());
        $unit->proverkaPrav($projectUser);
        $unit->proveritZnacheniePeremenihDeistviya($command->actionId, $command->values);
        $jobId = $this->runnerService->nachatVipolnenieDeistviya($unit, $project, $command->actionId, $command->values);
        $unit->vipolnitDeistvie($jobId, $command, $this->clock->now()->getTimestamp());
        $this->unitRepository->save($unit);
    }
}
