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
     * @param list<string>          $projectIds
     * @param array<string, string> $projectBranches переопределения ветки по projectId
     * @return list<ProektDlyaWorkspaceReadModel>
     * @throws \DomainException opencode.workspace.project_conflict|opencode.workspace.project_not_found|opencode.workspace.branch_protected
     */
    public function handle(
        array $projectIds,
        string $accountId,
        string $branch = '',
        array $projectBranches = [],
    ): array {
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

            $vetkaProekta = $projectBranches[$projectId] ?? $branch;

            if ($vetkaProekta !== '' && $project->soderzhitZashishennuyuVetku($vetkaProekta)) {
                // Имя проекта в detail помогает пользователю понять, какой
                // именно проект запрещает выбранную ветку.
                throw new \DomainException('opencode.workspace.branch_protected: '.$project->getName());
            }

            $result[] = new ProektDlyaWorkspaceReadModel(
                projectId: $project->getId(),
                projectName: $project->getName(),
                projectCode: $project->getCode(),
                mainBranch: $project->getMainBranchName(),
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
