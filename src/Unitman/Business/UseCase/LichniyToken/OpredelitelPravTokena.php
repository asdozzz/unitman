<?php

declare(strict_types=1);

namespace App\Unitman\Business\UseCase\LichniyToken;

use App\Unitman\Business\Model\LichniyToken\ObyomPrav;
use App\Unitman\Business\Model\Repo;
use App\Unitman\Business\Port\Project\CanGetProjectList;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Project\UmeetProveryatPushTokenom;
use App\Unitman\Business\ReadModel\ProjectList;

/**
 * Определение объёма прав личного токена по активным проектам Repo.
 *
 * Живая проверка push-прав идёт пер-проектно (универсальный запасной вариант);
 * ошибки storage API трактуются как отсутствие прав по конкретному проекту,
 * а не как невалидность токена — токен валидируется отдельно.
 */
final class OpredelitelPravTokena
{
    public function __construct(
        private CanGetProjectList $projectList,
        private ProjectRepository $projectRepository,
        private UmeetProveryatPushTokenom $proverkaPusha,
    ) {
    }

    /**
     * @return array{obyomPrav: ObyomPrav, proektySPushom: string[]}
     */
    public function opredelit(Repo $repo, string $token): array
    {
        $activeProjects = array_values(array_filter(
            $this->projectList->getListByRepoId($repo->getId()),
            static fn (ProjectList $project): bool => $project->isActive,
        ));

        $proektySPushom = [];

        foreach ($activeProjects as $project) {
            try {
                $aggregate = $this->projectRepository->getById($project->id);

                if ($this->proverkaPusha->canPushTokenom($token, $repo, $aggregate)) {
                    $proektySPushom[] = $project->id;
                }
            } catch (\Throwable) {
                // Проект недоступен под этим токеном или API ответил ошибкой:
                // права на него не фиксируем.
            }
        }

        if ($activeProjects !== [] && count($proektySPushom) === count($activeProjects)) {
            return [
                'obyomPrav' => ObyomPrav::Vse,
                'proektySPushom' => [],
            ];
        }

        return [
            'obyomPrav' => ObyomPrav::Spisok,
            'proektySPushom' => $proektySPushom,
        ];
    }
}
