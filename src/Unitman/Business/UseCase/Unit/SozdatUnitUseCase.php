<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\SozdatUnit;
use App\Unitman\Business\Model\Unit;
use App\Unitman\Business\Port\CanFindUnitDouble;
use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Business\Port\ProjectRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\Port\UnitRepository;

final class SozdatUnitUseCase
{
    public function __construct(
        private CanFindUnitDouble      $canFindUnitDouble,
        private ProjectRepository      $projectRepository,
        private UnitmanSecurityService $securityService,
        private CanGeneateGuid         $uuidGenerator,
        private UnitRepository         $unitRepository
    )
    {
    }

    function handle(SozdatUnit $command): void
    {
        if ($this->canFindUnitDouble->isExistsDoubleByName($command->unitName)) {
            throw new \DomainException('unit.double');
        }

        $project = $this->projectRepository->getById($command->projectId);

        if ($project->isDisable()) {
            throw new \DomainException('unit.project_is_disabled');
        }

        $userId = $this->securityService->getCurrentUserId();

        if (!$project->esliRazreshenoSobiratUniti($userId)) {
            throw new \DomainException('unit.ne_hvataet_prav');
        }

        $unit = Unit::sozdatUnit($this->uuidGenerator->makeGuid(), $userId, $project->getName(), $command);
        $this->unitRepository->save($unit);
    }
}
