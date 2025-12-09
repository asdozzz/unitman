<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\SozdatUnit;
use App\Unitman\Business\Model\Unit;
use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Unit\CanFindUnitDouble;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Utils\Exception\TranslatorKeyword;

final class SozdatUnitUseCase
{
    public function __construct(
        private CanFindUnitDouble      $canFindUnitDouble,
        private ProjectRepository      $projectRepository,
        private UnitmanSecurityService $securityService,
        private CanGeneateGuid         $uuidGenerator,
        private UnitRepository         $unitRepository,
    )
    {
    }

    function handle(SozdatUnit $command): string
    {
        if ($this->canFindUnitDouble->isExistsDoubleByName($command->projectId, $command->unitName)) {
            throw new \DomainException(TranslatorKeyword::UNIT_NAME_ALREADY_EXISTS->value);
        }

        $project = $this->projectRepository->getById($command->projectId);

        if ($project->isDisable()) {
            throw new \DomainException('unit.project_is_disabled');
        }

        $userId = $this->securityService->getCurrentUserId();

        $unitId = $this->uuidGenerator->makeGuid();
        $prozesId = $this->uuidGenerator->makeGuid();
        $unit = Unit::sozdatUnit($unitId, $userId, $prozesId, $command);
        $this->unitRepository->save($unit);

        return $unit->getId();
    }
}
