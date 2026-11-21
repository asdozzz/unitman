<?php

declare(strict_types=1);

namespace App\Unitman\Business\UseCase\Unit\Redaktor;

use App\Unitman\Business\Command\Unit\Redaktor\VykluchitRedaktirovanieUnita;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\Unit\UnitRepository;

/**
 * Выключение режима редактирования файлов юнита.
 *
 * Право — автор либо админ (15.8-б); выключение не требует личного токена.
 * Повторное выключение идемпотентно (15.10-а).
 */
final class VykluchitRedaktirovanieUnitaUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private ProjectRepository $projectRepository,
        private PravilaRedaktora $pravilaRedaktora,
        private RunnerService $runnerService,
    )
    {
    }

    public function handle(VykluchitRedaktirovanieUnita $command): void
    {
        $unit = $this->unitRepository->getById($command->unitId);
        $project = $this->projectRepository->getById($unit->getProjectId());

        $this->pravilaRedaktora->trebovatPravoUpravleniya($unit, $project);

        if (!$unit->getRedaktirovanieVklucheno()) {
            return;
        }

        $unit->vykluchitRedaktirovanie($project);
        $this->unitRepository->save($unit);

        if ($unit->esliPodgotovlen()) {
            $this->runnerService->izmenitRezhimRedaktora($unit->getId(), 'view');
        }
    }
}
