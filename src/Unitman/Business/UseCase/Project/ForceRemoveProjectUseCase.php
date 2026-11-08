<?php

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Command\Project\ForceRemoveProject;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\Port\VolumeType\VolumeTypeFileRepository;
use App\Unitman\Business\Port\VolumeType\VolumeTypeRepository;

final class ForceRemoveProjectUseCase
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private UnitmanSecurityService $securityService,
        private VolumeTypeFileRepository $volumeTypeFileRepository,
        private VolumeTypeRepository $volumeTypeRepository,
    )
    {
    }

    public function handle(ForceRemoveProject $command): void
    {
        if (!$this->securityService->isAdmin()) {
            throw new \Exception('security.access_denied');
        }

        $project = $this->projectRepository->getById($command->id);
        $project->removeManually();
        $this->projectRepository->save($project);

        // Каталог типов томов живёт вне event store: при форс-удалении проекта
        // удаляем связи с файлами и сами типы (файлы в медиатеке остаются).
        $this->volumeTypeFileRepository->udalitPoProektu($command->id);
        $this->volumeTypeRepository->udalitPoProektu($command->id);
    }
}
