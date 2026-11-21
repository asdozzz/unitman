<?php

declare(strict_types=1);

namespace App\Unitman\Business\UseCase\Unit\Redaktor;

use App\Unitman\Business\Command\Unit\Redaktor\VklyuchitRedaktirovanieUnita;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\Unit\UnitRepository;

/**
 * Включение режима редактирования файлов юнита.
 *
 * Право — автор либо админ с личным токеном на проект (15.8-б); личный
 * токен включающего обязателен (15.3-а) и живой перепроверяется (15.4-б).
 * Повторное включение идемпотентно (15.10-а).
 */
final class VklyuchitRedaktirovanieUnitaUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private ProjectRepository $projectRepository,
        private PravilaRedaktora $pravilaRedaktora,
        private RunnerService $runnerService,
    )
    {
    }

    public function handle(VklyuchitRedaktirovanieUnita $command): void
    {
        $unit = $this->unitRepository->getById($command->unitId);
        $project = $this->projectRepository->getById($unit->getProjectId());

        $actorId = $this->pravilaRedaktora->trebovatPravoUpravleniya($unit, $project);

        if ($unit->getRedaktirovanieVklucheno()) {
            return;
        }

        if ($unit->isSozdanSystemoi()) {
            throw new \DomainException('unit.redaktor.ne_avtor');
        }

        $this->pravilaRedaktora->proveritZhivoyToken($unit->getId(), $actorId, $project);

        $unit->vklyuchitRedaktirovanie($project);
        $this->unitRepository->save($unit);

        if ($unit->esliPodgotovlen()) {
            $this->runnerService->izmenitRezhimRedaktora($unit->getId(), 'edit');
        }
    }
}
