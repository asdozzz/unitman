<?php

declare(strict_types=1);

namespace App\Skill\Business\UseCase;

use App\Skill\Business\Command\OtkrepitNavykOtProekta;
use App\Skill\Business\Port\NavykRepository;
use App\Skill\Business\Utils\ProverkaPravNavykov;

/**
 * Открепление проектного навыка от проекта.
 *
 * Последний проект открепить нельзя: проектный навык обязан иметь хотя бы
 * одну привязку (иначе он стал бы недостижим). Удалять такой навык следует
 * архивацией.
 */
final class OtkrepitNavykOtProektaUseCase
{
    public function __construct(
        private readonly NavykRepository $repository,
        private readonly ProverkaPravNavykov $prava,
    ) {
    }

    /**
     * @return array{projectIds: list<string>}
     */
    public function handle(OtkrepitNavykOtProekta $command): array
    {
        $navyk = $this->repository->findById(trim($command->id));

        if ($navyk === null) {
            throw new \DomainException('skill.not_found');
        }

        if ($navyk->isGlobal()) {
            throw new \DomainException('skill.scope.global_cannot_detach');
        }

        $projectId = trim($command->projectId);
        $tekushie = $navyk->getProjectIds();

        if (!\in_array($projectId, $tekushie, true)) {
            throw new \DomainException('skill.project.not_attached');
        }

        if (\count($tekushie) <= 1) {
            throw new \DomainException('skill.project.required');
        }

        $this->prava->trebovatAdministratoraProektov($tekushie);

        $this->repository->detachFromProject($navyk->getId(), $projectId);

        $ostavshiesya = array_values(array_filter(
            $tekushie,
            static fn (string $id): bool => $id !== $projectId,
        ));

        return ['projectIds' => $ostavshiesya];
    }
}
