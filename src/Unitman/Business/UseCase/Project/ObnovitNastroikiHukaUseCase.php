<?php

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Command\Project\ObnovitNastroikiHuka;
use App\Unitman\Business\Port\Project\ProjectRepository;

final class ObnovitNastroikiHukaUseCase
{
    public function __construct(
        private ProjectRepository $projectRepository
    )
    {
    }

    function handle(ObnovitNastroikiHuka $command): void
    {
        $proekt = $this->projectRepository->getById($command->id);
        $proekt->obnovitNastrokiHuka($command);
        $this->projectRepository->save($proekt);
    }
}
