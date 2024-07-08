<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\PoluchitPeremenieUnita;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\ReadModel\Unit\PeremenyaUnita;

final class PoluchitPeremenieUnitaQuery
{
    public function __construct(
        private UnitRepository $unitRepository,
        private ProjectRepository $projectRepository,
        private UnitmanSecurityService $securityService)
    {
    }

    function handle(PoluchitPeremenieUnita $command): array
    {
        $unit = $this->unitRepository->getById($command->id);
        $project = $this->projectRepository->getById($unit->getProjectId());
        $projectUser = $project->getProjectUserById($this->securityService->getCurrentUserId());
        $unit->proverkaPrav($projectUser);
        $config = $unit->getConfig();

        if (empty($config)) {
            throw new \DomainException('unit.config_not_found');
        }

        $znacheniePeremenih = $unit->poluchitZnacheniyaPeremenih();

        $znacheniePeremenihMap = [];
        foreach ($znacheniePeremenih as $item) {
            $znacheniePeremenihMap[$item->getId()] = $item->getValue();
        }

        $result = [];
        foreach ($config->getVariables() as $variable) {
            $value = $znacheniePeremenihMap[$variable->getId()] ?? "";
            $result[] = (new PeremenyaUnita($variable, $value))->toArray();
        }

        return $result;
    }
}
