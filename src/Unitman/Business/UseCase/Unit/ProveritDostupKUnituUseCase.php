<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Port\Project\UmeetPoluchatSpisokProektovDlyPolzovatelya;
use App\Unitman\Business\Port\Unit\UnitRepository;

final class ProveritDostupKUnituUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private UmeetPoluchatSpisokProektovDlyPolzovatelya $umeetPoluchatSpisokProektovDlyPolzovatelya,
    )
    {
    }

    /**
     * Доступ к юниту определяется доступом к его проекту — тот же приём,
     * что и при создании юнита. Отсутствующий юнит недоступен никому.
     */
    function handle(string $unitId, string $accountId): bool
    {
        $unitId = trim($unitId);
        $accountId = trim($accountId);

        if ($unitId === '' || $accountId === '') {
            return false;
        }

        try {
            $unit = $this->unitRepository->getById($unitId);
        } catch (\DomainException) {
            return false;
        }

        $projectId = $unit->getProjectId();

        foreach ($this->umeetPoluchatSpisokProektovDlyPolzovatelya->poluchitSpisokProektovDlyPolzovatelya($accountId) as $project) {
            if (($project['id'] ?? null) === $projectId) {
                return true;
            }
        }

        return false;
    }
}
