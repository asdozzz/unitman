<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\SobratUnit;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;
use Symfony\Component\Clock\ClockInterface;

final class SobratUnitUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private ProjectRepository $projectRepository,
        private RunnerService $runnerService,
        private UnitmanSecurityService $securityService,
        private ClockInterface         $clock
    )
    {
    }

    function handle(SobratUnit $command): void
    {
        $unit = $this->unitRepository->getById($command->id);
        $project = $this->projectRepository->getById($unit->getProjectId());
        $projectUser = $project->getProjectUserById($this->securityService->getCurrentUserId());
        $unit->proverkaPrav($projectUser);
        $jobId = $this->runnerService->nachatSborkuUnita($unit);
        $unixtime = $this->clock->now()->getTimestamp();
        $unit->nachatSborkuUnita($jobId, $unixtime);
        $this->unitRepository->save($unit);
    }
}
