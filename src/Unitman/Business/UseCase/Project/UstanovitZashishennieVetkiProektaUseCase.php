<?php

declare(strict_types=1);

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Command\Project\UstanovitZashishennieVetkiProekta;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;

final class UstanovitZashishennieVetkiProektaUseCase
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly UnitmanSecurityService $securityService
    )
    {
    }

    public function handle(UstanovitZashishennieVetkiProekta $command): void
    {
        if (!$this->securityService->isAdmin()) {
            throw new \Exception('security.access_denied');
        }

        $project = $this->projectRepository->getById($command->id);
        $project->ustanovitZashishennieVetki($command);
        $this->projectRepository->save($project);
    }
}
