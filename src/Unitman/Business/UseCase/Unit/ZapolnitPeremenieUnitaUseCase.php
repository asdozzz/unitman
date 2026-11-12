<?php

declare(strict_types=1);

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\ZapolnitPeremenieUnita;
use App\Unitman\Business\Command\Unit\ZapolnitPeremenieUnita\SvyazPeremenoi;
use App\Unitman\Business\Model\Unit\PeremennayaSvyaz;
use App\Unitman\Business\Port\CanGenerateGuid;
use App\Unitman\Business\Port\MediatekaService;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Project\UmeetPoluchatSpisokProektovDlyPolzovatelya;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;

final class ZapolnitPeremenieUnitaUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private ProjectRepository $projectRepository,
        private UnitmanSecurityService $securityService,
        private CanGenerateGuid $canGenerateGuid,
        private MediatekaService $mediatekaService,
        private ProverkaFaylovVolumov $proverkaFaylovVolumov,
        private ProverkaSvyazeyPeremenih $proverkaSvyazeyPeremenih,
        private UmeetPoluchatSpisokProektovDlyPolzovatelya $umeetPoluchatSpisokProektovDlyPolzovatelya,
    )
    {
    }

    function handle(ZapolnitPeremenieUnita $command): void
    {
        $unit = $this->unitRepository->getById($command->id);
        $project = $this->projectRepository->getById($unit->getProjectId());
        $userId = $this->securityService->getCurrentUserId();
        $projectUser = $project->getProjectUserById($userId);
        $unit->proverkaPrav($projectUser);

        // Файлы томов: черновики прикрепляются к юниту, архивы каталога проекта
        // используются повторно, файлы этого юнита разрешены без перепривязки.
        // Наличие `dir` в конфиге валидирует сам агрегат.
        $chernoviki = $this->proverkaFaylovVolumov->proverit($unit->getProjectId(), $command->volumes, $unit->getId());

        $svyazi = array_map(
            fn(SvyazPeremenoi $svyaz): PeremennayaSvyaz => new PeremennayaSvyaz($svyaz->variableId, $svyaz->targetUnitId),
            $command->svyazi
        );

        // Пустой список связей означает «связи не меняются» — проверять нечего.
        if ($svyazi !== []) {
            $this->proverkaSvyazeyPeremenih->proverit(
                $svyazi,
                $unit->getId(),
                array_map(
                    fn(array $project): string => $project['id'],
                    $this->umeetPoluchatSpisokProektovDlyPolzovatelya->poluchitSpisokProektovDlyPolzovatelya($userId)
                )
            );
        }

        $unit->zapolnitPeremenie($command->values, $command->volumes, $svyazi);
        if ($unit->esliZapushen() || $unit->esliPodgotovlen()) {
            $prozesId = $this->canGenerateGuid->makeGuid();
            $unit->dobavitProzesObnovleniya($userId, $prozesId, true, $project->poluchitNastroikiHuka()->obnovlenieBezSbrosaPodgotovki);
        }
        $this->unitRepository->save($unit);

        if ($chernoviki !== []) {
            $this->mediatekaService->prikrepitFaylyKUnitu($chernoviki, $unit->getId());
        }
    }
}
