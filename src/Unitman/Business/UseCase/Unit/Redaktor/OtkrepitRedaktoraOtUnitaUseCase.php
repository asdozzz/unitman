<?php

declare(strict_types=1);

namespace App\Unitman\Business\UseCase\Unit\Redaktor;

use App\Unitman\Business\Command\Unit\Redaktor\OtkrepitRedaktoraOtUnita;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Unit\UnitRepository;

/**
 * Открепление редактора от юнита.
 *
 * Право — автор, админ (15.8-б) либо сам редактор.
 */
final class OtkrepitRedaktoraOtUnitaUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private ProjectRepository $projectRepository,
        private PravilaRedaktora $pravilaRedaktora,
    )
    {
    }

    public function handle(OtkrepitRedaktoraOtUnita $command): void
    {
        $unit = $this->unitRepository->getById($command->unitId);
        $project = $this->projectRepository->getById($unit->getProjectId());

        $targetId = trim($command->userId);
        $actorId = $this->pravilaRedaktora->tekushiyPolzovatel();

        if ($actorId !== $unit->getAuthorId() && $actorId !== $targetId) {
            $this->pravilaRedaktora->trebovatPravoUpravleniya($unit, $project);
            // Админ открепляет, только имея личный токен на проект.
            $this->pravilaRedaktora->proveritZhivoyToken($unit->getId(), $actorId, $project);
        }

        $unit->otkrepitRedaktora($targetId, $project);
        $this->unitRepository->save($unit);
    }
}
