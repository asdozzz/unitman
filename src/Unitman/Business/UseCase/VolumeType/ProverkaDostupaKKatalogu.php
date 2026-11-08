<?php

declare(strict_types=1);

namespace App\Unitman\Business\UseCase\VolumeType;

use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\Project\ProjectUserRole;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;

/**
 * Проверка прав на каталог типов томов проекта.
 *
 * Членство в проекте обязательно для чтения; управление типами и связями
 * с файлами доступно только `ProjectUserRole::ADMIN` проекта.
 */
final class ProverkaDostupaKKatalogu
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly UnitmanSecurityService $securityService
    ) {
    }

    public function proveritChlenstvoProekta(string $projectId): Project
    {
        $project = $this->projectRepository->getById($projectId);
        $project->getProjectUserById($this->securityService->getCurrentUserId());

        return $project;
    }

    public function proveritPravaAdmina(string $projectId): Project
    {
        $project = $this->proveritChlenstvoProekta($projectId);
        $projectUser = $project->getProjectUserById($this->securityService->getCurrentUserId());

        if ($projectUser->userRole !== ProjectUserRole::ADMIN) {
            throw new \DomainException('security.access_denied');
        }

        return $project;
    }
}
