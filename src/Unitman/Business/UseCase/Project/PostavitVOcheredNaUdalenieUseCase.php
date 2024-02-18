<?php

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Command\Project\PostavitVOcheredNaUdalenie;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\UnitmanSecurityService;

final class PostavitVOcheredNaUdalenieUseCase
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private RunnerService $runnerService,
        private UnitmanSecurityService $securityService
    )
    {
    }

    public function handle(PostavitVOcheredNaUdalenie $command): void
    {
        if (!$this->securityService->isAdmin()) {
            throw new \Exception('security.access_denied');
        }

        $project = $this->projectRepository->getById($command->id);

        if ($project->esliProektBilSobran()) {
            $result = $this->runnerService->removeProject($project);
            $project->postavitVOcheredNaUdanlenie($result->jobId);
            if ($result->success) {
                $project->successfullyRemoving($result->info);
            } else {
                $project->errorWhenRemoving($result->info);
            }
        } else {
            $project->postavitVOcheredNaUdanlenie('STUB_FOR_NEW_PROJECT');
            $project->successfullyRemoving('');
        }


        $this->projectRepository->save($project);
    }
}
