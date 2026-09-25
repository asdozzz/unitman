<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\SozdatUnit;
use App\Unitman\Business\Model\Unit;
use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Unit\CanFindUnitDouble;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;

final class SozdatUnitSystemoiUseCase
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

    function handle(string $projectId, string $vetka): string
    {
        $project = $this->projectRepository->getById($projectId);

        if ($project->isDisable()) {
            throw new \DomainException('unit.project_is_disabled');
        }

        if (!$project->poluchitNastroikiHuka()->avtosozdanie) {
            throw new \DomainException('unit.avtosozdanie_viklucheno');
        }
        $unitName = preg_replace('/[^a-zA-Z0-9_]+/misu', '_',$vetka) ?: "";
        $memoryLimit = $project->poluchitMemoryLimit();
        $command = new SozdatUnit($projectId, $unitName, $vetka, [], $memoryLimit);

        if ($this->canFindUnitDouble->isExistsDoubleByName($projectId, $command->unitName)) {
            throw new \DomainException('unit.double');
        }

        $systemUser = $this->securityService->getSystemUser();
        $unitId = $this->uuidGenerator->makeGuid();
        $prozesId = $this->uuidGenerator->makeGuid();
        $unit = Unit::sozdatUnitSystemoi($unitId, $systemUser, $prozesId, $command);
        $this->unitRepository->save($unit);
        return $unit->getId();
    }
}
