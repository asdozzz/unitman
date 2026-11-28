<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UseCase;

use App\OpenCode\Business\Command\DobavitProektyVWorkspace;
use App\OpenCode\Business\Model\ProektWorkspace;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\Model\Workspace;
use App\OpenCode\Business\Model\WorkspacePendingUpdate;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatProektamiWorkspace;
use App\OpenCode\Business\Port\UmeetUpravlyatWorkspace;
use App\OpenCode\Business\Workspace\Model\WorkspaceFeatureFlags;
use App\OpenCode\Business\Workspace\UseCase\DobavitProektyVWorkspaceSessiiUseCase;

/**
 * Добавление проектов к работающему workspace.
 *
 * Проекты не попадают в снимок сразу: сначала runner клонирует их в контейнер,
 * и только после успеха фоновая задача сохраняет снимок.
 */
final class DobavitProektyVWorkspaceUseCase
{
    private const BRANCH_MAX_LENGTH = 256;

    public function __construct(
        private readonly SessiyaOpenCodeRepository $sessionRepository,
        private readonly UmeetProveryatRekvizityOpenCode $credentials,
        private readonly UmeetUpravlyatProektamiWorkspace $projectami,
        private readonly UmeetUpravlyatWorkspace $workspaceManager,
        private readonly ?WorkspaceFeatureFlags $flags = null,
        private readonly ?DobavitProektyVWorkspaceSessiiUseCase $workspaceUseCase = null,
    ) {
    }

    public function handle(DobavitProektyVWorkspace $command): void
    {
        if ($this->flags?->pisatVNoviyAgregat() === true && $this->workspaceUseCase !== null) {
            $this->workspaceUseCase->handle($command);

            return;
        }

        $accountId = $this->credentials->tekuschiiAccountId();
        $session = $this->sessionRepository->getById(trim($command->workspaceId));
        $workspace = $this->trebovatWorkspace($session);

        if ($workspace->getStatus() !== StatusWorkspace::RUNNING) {
            throw new \DomainException('opencode.workspace.not_running');
        }

        if ($this->sessionRepository->findWorkspaceUpdate($session->getId()) !== null) {
            throw new \DomainException('opencode.workspace.update_in_progress');
        }

        $projects = $this->normalizeProjects($command->projects, $workspace);

        if (count($workspace->getProjects()) + count($projects) > SessiyaOpenCode::MAX_WORKSPACE_PROJECTS) {
            throw new \DomainException('opencode.workspace.too_many_projects');
        }

        $projectBranches = [];
        foreach ($projects as $project) {
            $projectBranches[$project['projectId']] = $project['branch'];
        }

        $snapshots = $this->projectami->poluchitProekti(
            array_keys($projectBranches),
            $accountId,
            $workspace->getBranch(),
            $projectBranches,
        );

        $normalized = $this->rasstavitPozicii($snapshots, count($workspace->getProjects()));
        $readOnly = $workspace->isReadOnly() || $this->estProektBezPusha($normalized);

        $pending = new WorkspacePendingUpdate(
            WorkspacePendingUpdate::KIND_PROJECT,
            $normalized,
            [],
            [],
            $readOnly,
        );

        $workflowId = $this->workspaceManager->obnovit($session, $pending);

        $this->sessionRepository->saveWorkspaceUpdate($session->getId(), $workflowId, $pending);
        $workspace->zapomnitOperaciyu(Workspace::OPERATION_UPDATE, $workflowId, time());
        $this->sessionRepository->update($session);
    }

    /**
     * @param list<array{projectId?: mixed, branch?: mixed}> $projects
     *
     * @return list<array{projectId: string, branch: string}>
     */
    private function normalizeProjects(array $projects, Workspace $workspace): array
    {
        $existing = [];
        foreach ($workspace->getProjects() as $project) {
            $existing[$project->projectId] = true;
        }

        $result = [];
        $seen = [];

        foreach ($projects as $project) {
            $projectId = is_array($project) && is_string($project['projectId'] ?? null)
                ? trim($project['projectId'])
                : '';
            $branch = is_array($project) && is_string($project['branch'] ?? null)
                ? trim($project['branch'])
                : '';

            if ($projectId === '') {
                continue;
            }

            if (isset($existing[$projectId]) || isset($seen[$projectId])) {
                throw new \DomainException('opencode.workspace.project_conflict');
            }

            if ($branch === '') {
                throw new \DomainException('opencode.workspace.branch_required');
            }

            if (mb_strlen($branch) > self::BRANCH_MAX_LENGTH) {
                throw new \DomainException('opencode.workspace.branch_too_long');
            }

            $seen[$projectId] = true;
            $result[] = ['projectId' => $projectId, 'branch' => $branch];
        }

        if ($result === []) {
            throw new \DomainException('opencode.workspace.projects_required');
        }

        return $result;
    }

    /**
     * @param list<ProektWorkspace> $projects
     *
     * @return list<ProektWorkspace>
     */
    private function rasstavitPozicii(array $projects, int $offset): array
    {
        $result = [];

        foreach ($projects as $project) {
            $result[] = new ProektWorkspace(
                $project->projectId,
                $project->projectName,
                $project->projectCode,
                $project->canPush,
                $offset + count($result),
                $project->mainBranch,
                $project->branch,
            );
        }

        return $result;
    }

    /**
     * @param list<ProektWorkspace> $projects
     */
    private function estProektBezPusha(array $projects): bool
    {
        foreach ($projects as $project) {
            if (!$project->canPush) {
                return true;
            }
        }

        return false;
    }

    private function trebovatWorkspace(SessiyaOpenCode $session): Workspace
    {
        $workspace = $session->getWorkspace();

        if (!$session->estWorkspace() || $workspace === null) {
            throw new \DomainException('opencode.workspace.not_found');
        }

        return $workspace;
    }
}
