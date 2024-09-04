<?php

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Command\Project\UdalitPeremenuyuIzProekta;
use App\Unitman\Business\Port\Project\ProjectRepository;

final class ProzessUdaleniyaPeremenoiIzProekta
{
    public function __construct(
        private ProjectRepository $projectRepository
    )
    {
    }
    function handle(UdalitPeremenuyuIzProekta $command): void
    {
        $project = $this->projectRepository->getById($command->projectId);
        $project->udalitPeremenuyu($command);
        $this->projectRepository->save($project);
    }
}
