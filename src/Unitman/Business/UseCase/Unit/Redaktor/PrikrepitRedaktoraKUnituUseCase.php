<?php

declare(strict_types=1);

namespace App\Unitman\Business\UseCase\Unit\Redaktor;

use App\Unitman\Business\Command\Unit\Redaktor\PrikrepitRedaktoraKUnitu;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;

/**
 * Прикрепление редактора к юниту.
 *
 * Прикрепляет автор либо админ (15.8-б). Целевой пользователь должен иметь
 * доступ к проекту (15.5-а) и личный токен с правами на него; токен живой
 * перепроверяется (15.4-б).
 */
final class PrikrepitRedaktoraKUnituUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private ProjectRepository $projectRepository,
        private PravilaRedaktora $pravilaRedaktora,
        private UnitmanSecurityService $securityService,
    )
    {
    }

    public function handle(PrikrepitRedaktoraKUnitu $command): void
    {
        $unit = $this->unitRepository->getById($command->unitId);
        $project = $this->projectRepository->getById($unit->getProjectId());

        $actorId = $this->pravilaRedaktora->trebovatPravoUpravleniya($unit, $project);

        if ($actorId !== $unit->getAuthorId()) {
            // Админ управляет редакторами, только имея личный токен на проект.
            $this->pravilaRedaktora->proveritZhivoyToken($unit->getId(), $actorId, $project);
        }

        $targetId = trim($command->userId);

        if ($targetId === '') {
            throw new \DomainException('unit.redaktor.redaktor_ne_nayden');
        }

        try {
            $this->securityService->getUserById($targetId);
        } catch (\Throwable) {
            throw new \DomainException('unit.redaktor.redaktor_ne_nayden');
        }

        $this->pravilaRedaktora->proveritDostupKProektu($targetId, $project->getId());

        try {
            $this->pravilaRedaktora->proveritZhivoyToken($unit->getId(), $targetId, $project);
        } catch (\DomainException $exception) {
            if (in_array($exception->getMessage(), [
                'unit.redaktor.net_lichnogo_tokena',
                'unit.redaktor.token_bez_prav_na_proekt',
            ], true)) {
                throw new \DomainException('unit.redaktor.redaktor_bez_tokena');
            }

            throw $exception;
        }

        $unit->prikrepitRedaktora($targetId, $project);
        $this->unitRepository->save($unit);
    }
}
