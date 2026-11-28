<?php

declare(strict_types=1);

namespace App\Skill\Business\UseCase;

use App\Skill\Business\Command\PoluchitSpisokNavykov;
use App\Skill\Business\Model\NavykOpenCode;
use App\Skill\Business\Port\NavykRepository;
use App\Skill\Business\ReadModel\NavykKratkoReadModel;
use App\Skill\Business\Utils\ProverkaPravNavykov;

/**
 * Список навыков.
 *
 * `scope=global` (или scope не задан без projectId) — глобальные навыки,
 * доступны любому аутентифицированному. `scope=project` — навыки проекта,
 * требуется членство в этом проекте.
 */
final class PoluchitSpisokNavykovQuery
{
    public function __construct(
        private readonly NavykRepository $repository,
        private readonly ProverkaPravNavykov $prava,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function handle(PoluchitSpisokNavykov $command): array
    {
        $this->prava->tekuschiiAccountId();

        $scope = $command->scope !== null ? strtolower(trim($command->scope)) : null;
        $projectId = $command->projectId !== null ? trim($command->projectId) : '';

        if ($scope !== null && !\in_array($scope, ['global', 'project'], true)) {
            throw new \DomainException('skill.scope.invalid');
        }

        $proektniy = $scope === 'project' || ($scope === null && $projectId !== '');

        if ($proektniy) {
            if ($projectId === '') {
                throw new \DomainException('skill.project.required');
            }

            $this->prava->trebovatChtenieProektnogo([$projectId]);
            $navyki = $this->repository->listForProjects([$projectId], $command->includeArchived);
        } else {
            if ($scope === 'global' && $projectId !== '') {
                throw new \DomainException('skill.scope.invalid');
            }

            $navyki = $this->repository->listGlobal($command->includeArchived);
        }

        return array_map(
            static fn (NavykOpenCode $navyk): array => NavykKratkoReadModel::izModela($navyk)->toArray(),
            $navyki,
        );
    }
}
