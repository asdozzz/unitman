<?php

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Command\Project\IzmenitZnacheniePeremenoiProekta;
use App\Unitman\Business\Port\Project\ProjectRepository;

final class ProzessIzmeneniyaZnacheniyaPeremenoiProekta
{
    public function __construct(
        private ProjectRepository $projectRepository
    )
    {
    }
    function handle(IzmenitZnacheniePeremenoiProekta $command): void
    {
        $project = $this->projectRepository->getById($command->projectId);
        $project->izmenitZnacheniePeremenoi($command);
        $this->projectRepository->save($project);
    }
}
