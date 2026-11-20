<?php

declare(strict_types=1);

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\Repo;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Project\UmeetPoluchatSpisokProektovDlyPolzovatelya;
use App\Unitman\Business\Port\Project\UmeetPoluchatUrlKlonaProekta;
use App\Unitman\Business\Port\Project\UmeetProveryatPushVProekt;
use App\Unitman\Business\Port\Repo\RepoRepository;
use App\Unitman\Business\ReadModel\Project\ProektDlyaWorkspaceReadModel;
use App\Unitman\Business\Utils\IdePort;

/**
 * Проекты для workspace-сессии OpenCode.
 */
final class PoluchitProektiDlyaWorkspaceQuery
{
    public function __construct(
        private UmeetPoluchatSpisokProektovDlyPolzovatelya $spisokProektovDlyPolzovatelya,
        private ProjectRepository $projectRepository,
        private RepoRepository $repoRepository,
        private UmeetProveryatPushVProekt $proverkaPusha,
        private UmeetPoluchatUrlKlonaProekta $urlKlonaProekta,
    ) {
    }

    /**
     * @param list<string> $projectIds
     * @return list<ProektDlyaWorkspaceReadModel>
     * @throws \DomainException opencode.workspace.project_conflict|opencode.workspace.project_not_found
     */
    public function handle(array $projectIds, string $accountId): array
    {
        $dostupnieProekti = $this->dostupnieProekti($accountId);

        $uvidennieProekti = [];
        $result = [];

        foreach ($projectIds as $projectId) {
            if (isset($uvidennieProekti[$projectId])) {
                throw new \DomainException('opencode.workspace.project_conflict');
            }
            $uvidennieProekti[$projectId] = true;

            if (!isset($dostupnieProekti[$projectId])) {
                throw new \DomainException('opencode.workspace.project_not_found');
            }

            try {
                $project = $this->projectRepository->getById($projectId);
                $repo = $this->repoRepository->getById($project->getRepoId());
            } catch (\DomainException) {
                throw new \DomainException('opencode.workspace.project_not_found');
            }

            $result[] = new ProektDlyaWorkspaceReadModel(
                projectId: $project->getId(),
                projectName: $project->getName(),
                projectCode: $project->getCode(),
                proxyHost: (string) $project->getProxyHost(),
                canPush: $this->proverkaPusha->canPush($repo, $project),
                storageUrl: $this->urlKlonaProekta->poluchitUrlKlonaProekta($repo, $project),
                idePort: IdePort::izOkruzheniya(),
            );
        }

        return $result;
    }

    /**
     * @return array<string, true>
     */
    private function dostupnieProekti(string $accountId): array
    {
        $result = [];

        foreach ($this->spisokProektovDlyPolzovatelya->poluchitSpisokProektovDlyPolzovatelya($accountId) as $proekt) {
            if (!is_array($proekt)) {
                continue;
            }

            $projectId = $proekt['id'] ?? null;
            if (is_string($projectId) && $projectId !== '') {
                $result[$projectId] = true;
            }
        }

        return $result;
    }
}
