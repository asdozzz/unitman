<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\Unit\UnitRepository;
use Psr\Clock\ClockInterface;

final class VipolnitDeistviyeUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private ProjectRepository $projectRepository,
        private RunnerService $runnerService,
        private ClockInterface $clock
    )
    {
    }
    function handle(string $unitId, string $jobId): void
    {
        $unit = $this->unitRepository->getById($unitId);
        $project = $this->projectRepository->getById($unit->getProjectId());
        $deistvie = $unit->poluchitDeistviePoJobId($jobId);
        $unit->vipolnitDeistvie($jobId, $this->clock->now()->getTimestamp());
        $this->runnerService->nachatVipolnenieDeistviya($jobId, $unit, $project, $deistvie['actionId'], $deistvie['values']);
        $this->unitRepository->save($unit);
    }
}
