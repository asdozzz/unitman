<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\SozdatUnit;
use App\Unitman\Business\Model\Unit;
use App\Unitman\Business\Model\Unit\PeremennayaSvyaz;
use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Business\Port\MediatekaService;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Project\UmeetPoluchatSpisokProektovDlyPolzovatelya;
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
        private MediatekaService       $mediatekaService,
        private ProverkaFaylovVolumov  $proverkaFaylovVolumov,
        private ProverkaSvyazeyPeremenih $proverkaSvyazeyPeremenih,
        private UmeetPoluchatSpisokProektovDlyPolzovatelya $umeetPoluchatSpisokProektovDlyPolzovatelya,
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

        // Связи переменных с юнитами, выбранными через хелпер, проверяются
        // до записи событий: цель должна существовать и быть доступной.
        $this->proverkaSvyazeyPeremenih->proverit(
            $this->makeSvyazi($command),
            null,
            array_map(
                fn(array $project): string => $project['id'],
                $this->umeetPoluchatSpisokProektovDlyPolzovatelya->poluchitSpisokProektovDlyPolzovatelya($userId)
            )
        );

        // Проверяем файлы томов до сохранения юнита: черновики будут привязаны
        // к новому юниту, архивы каталога проекта остаются общими.
        $chernoviki = $this->proverkaFaylovVolumov->proverit($command->projectId, $command->volumes);

        $unitId = $this->uuidGenerator->makeGuid();
        $prozesId = $this->uuidGenerator->makeGuid();
        $unit = Unit::sozdatUnit($unitId, $userId, $prozesId, $command);
        $this->unitRepository->save($unit);

        if ($chernoviki !== []) {
            $this->mediatekaService->prikrepitFaylyKUnitu($chernoviki, $unitId);
        }

        return $unit->getId();
    }

    /**
     * @return PeremennayaSvyaz[]
     */
    private function makeSvyazi(SozdatUnit $command): array
    {
        $result = [];

        foreach ($command->znacheniePeremenoi as $peremenaya) {
            $targetUnitId = $peremenaya->targetUnitId;

            if ($targetUnitId === null || trim($targetUnitId) === '') {
                continue;
            }

            $result[] = new PeremennayaSvyaz($peremenaya->id, $targetUnitId);
        }

        return $result;
    }
}
