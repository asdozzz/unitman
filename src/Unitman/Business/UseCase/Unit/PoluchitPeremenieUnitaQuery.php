<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\PoluchitPeremenieUnita;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\ReadModel\Unit\PeremenyaUnita;

final class PoluchitPeremenieUnitaQuery
{
    public function __construct(private UnitRepository $unitRepository,private UnitmanSecurityService $securityService)
    {
    }

    function handle(PoluchitPeremenieUnita $command): array
    {
        $unit = $this->unitRepository->getById($command->id);
        $unit->proverkaPrav($this->securityService->getCurrentUserId());
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
