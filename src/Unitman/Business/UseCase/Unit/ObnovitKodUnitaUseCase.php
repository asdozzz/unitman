<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\ObnovitKodUnita;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;
use Symfony\Component\Clock\ClockInterface;

final class ObnovitKodUnitaUseCase
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

    function handle(ObnovitKodUnita $command): void
    {
        $unit = $this->unitRepository->getById($command->id);
        $project = $this->projectRepository->getById($unit->getProjectId());
        $projectUser = $project->getProjectUserById($this->securityService->getCurrentUserId());
        $unit->proverkaPrav($projectUser);
        $jobId = $this->runnerService->nachatObnovlenieUnita($unit);
        $unixtime = $this->clock->now()->getTimestamp();
        $unit->nachatObnovlenieUnita($jobId, $unixtime);
        $this->unitRepository->save($unit);
    }

    function handleSystem(ObnovitKodUnita $command): void
    {
        $unit = $this->unitRepository->getById($command->id);
        $project = $this->projectRepository->getById($unit->getProjectId());
        $projectUser = $project->getProjectUserById($unit->getAuthorId());
        $unit->proverkaPrav($projectUser);
        $jobId = $this->runnerService->nachatObnovlenieUnita($unit);
        $unixtime = $this->clock->now()->getTimestamp();
        $unit->nachatObnovlenieUnita($jobId, $unixtime);
        $this->unitRepository->save($unit);
    }
}
