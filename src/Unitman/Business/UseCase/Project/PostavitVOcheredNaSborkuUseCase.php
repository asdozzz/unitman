<?php

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Command\Project\PostavitVOcheredNaSborku;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\UnitmanSecurityService;

final class PostavitVOcheredNaSborkuUseCase
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private RunnerService $runnerService,
        private UnitmanSecurityService $securityService
    )
    {
    }

    public function handle(PostavitVOcheredNaSborku $command): void
    {
        if (!$this->securityService->isAdmin()) {
            throw new \Exception('security.access_denied');
        }

        $project = $this->projectRepository->getById($command->id);

        $errs = $project->proverkaProektaDlyNachlaSborki();

        if (!empty($errs)) {
            throw new \DomainException($errs[0]);
        }

        $jobId = $this->runnerService->nachatSborkuProekta($project);
        $project->postavitVOcheredNaSborku((string)$jobId);

        $this->projectRepository->save($project);
    }
}
