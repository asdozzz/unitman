<?php

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Command\Project\DobavitPeremenuyuVProekt;
use App\Unitman\Business\Port\Project\ProjectRepository;

final class ProzessDobavleniyaPeremenoiVProekt
{
    public function __construct(
        private ProjectRepository $projectRepository
    )
    {
    }
    function handle(DobavitPeremenuyuVProekt $command): void
    {
        $project = $this->projectRepository->getById($command->projectId);
        $project->dobavitPeremenuyu($command);
        $this->projectRepository->save($project);
    }
}
