<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\PoluchitSsylkuPoPomoschniku;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Unit\CanGetUnitReadModelById;
use App\Unitman\Business\Port\UnitmanSecurityService;

final class PoluchitSsylkuPoPomoschnikuQuery
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private CanGetUnitReadModelById $canGetUnitReadModelById,
        private UnitmanSecurityService $securityService
    )
    {
    }

    function handle(PoluchitSsylkuPoPomoschniku $command): array
    {
        $project = $this->projectRepository->getById($command->projectId);
        $project->getProjectUserById($this->securityService->getCurrentUserId());

        $targetUnit = $this->canGetUnitReadModelById->getById($command->targetUnitId);

        /**
         * @var array{service: string, path: string, port: int|string, protocol: string, startUri: ?string} $link
         */
        foreach ($targetUnit->links as $link) {
            if ($link['service'] === $command->service
                && $link['protocol'] === 'http'
                && (int)$link['port'] === $command->port
            ) {
                return ['link' => $link['path'].($command->suffix ?? '')];
            }
        }

        throw new \DomainException('unit.pomoschnik.servis_ne_naiden');
    }
}
