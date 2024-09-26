<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\SozdatUnit;
use App\Unitman\Business\Model\Unit;
use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Unit\CanFindUnitDouble;
use App\Unitman\Business\Port\Unit\UmeetSobiratUnit;
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
        private UmeetSobiratUnit $umeetSobiratUnit
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

        $unitName = preg_replace('/[^a-zA-Z0-9_]+/misu', '_',$vetka);

        $command = new SozdatUnit($projectId, $unitName, $vetka);

        if ($this->canFindUnitDouble->isExistsDoubleByName($projectId, $command->unitName)) {
            throw new \DomainException('unit.double');
        }

        $systemUser = $this->securityService->getSystemUser();

        $unit = Unit::sozdatUnitSystemoi($this->uuidGenerator->makeGuid(), $systemUser, $command);
        $this->unitRepository->save($unit);

        $jobId = $this->umeetSobiratUnit->sobratUnitOtLizaSystemi($unit->getId());

        $unit = $this->unitRepository->getById($unit->getId());
        $unit->nachatAvtosborku($jobId);
        $this->unitRepository->save($unit);

        return $unit->getId();
    }
}
