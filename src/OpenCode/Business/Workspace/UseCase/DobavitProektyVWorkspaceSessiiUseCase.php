<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Workspace\UseCase;

use App\OpenCode\Business\Command\DobavitProektyVWorkspace;
use App\OpenCode\Business\Model\ProektWorkspace;
use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\Model\Workspace;
use App\OpenCode\Business\Model\WorkspacePendingUpdate;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatProektamiWorkspace;
use App\OpenCode\Business\Port\UmeetUpravlyatWorkspace;
use App\OpenCode\Business\Workspace\Port\WorkspaceSessionRepository;

/**
 * Добавление проектов к работающему workspace на новом агрегате.
 *
 * Проекты не попадают в снимок сразу: сначала Runner клонирует их, снимок
 * обновится после успешного завершения операции.
 */
final class DobavitProektyVWorkspaceSessiiUseCase
{
    private const BRANCH_MAX_LENGTH = 256;

    public function __construct(
        private readonly WorkspaceSessionRepository $workspaces,
        private readonly UmeetProveryatRekvizityOpenCode $credentials,
        private readonly UmeetUpravlyatProektamiWorkspace $projectami,
        private readonly UmeetUpravlyatWorkspace $workspaceManager,
    ) {
    }

    public function handle(DobavitProektyVWorkspace $command): void
    {
        $accountId = $this->credentials->tekuschiiAccountId();
        $workspace = $this->workspaces->getById(trim($command->workspaceId));
        $state = $workspace->getWorkspace();

        if ($state->getStatus() !== StatusWorkspace::RUNNING) {
            throw new \DomainException('opencode.workspace.not_running');
        }

        if ($this->workspaces->findPendingUpdate($workspace->getId()) !== null) {
            throw new \DomainException('opencode.workspace.update_in_progress');
        }

        $projects = $this->normalizeProjects($command->projects, $state);

        if (count($state->getProjects()) + count($projects) > Workspace::MAX_PROJECTS) {
            throw new \DomainException('opencode.workspace.too_many_projects');
        }

        $projectBranches = [];
        foreach ($projects as $project) {
            $projectBranches[$project['projectId']] = $project['branch'];
        }

        $snapshots = $this->projectami->poluchitProekti(
            array_keys($projectBranches),
            $accountId,
            $state->getBranch(),
            $projectBranches,
        );

        $normalized = $this->rasstavitPozicii($snapshots, count($state->getProjects()));
        $readOnly = $state->isReadOnly() || $this->estProektBezPusha($normalized);

        $pending = new WorkspacePendingUpdate(
            WorkspacePendingUpdate::KIND_PROJECT,
            $normalized,
            [],
            [],
            $readOnly,
        );

        $workflowId = $this->workspaceManager->obnovit($workspace, $pending);

        $this->workspaces->savePendingUpdate(
            $workspace->getId(),
            $workflowId,
            $pending->toArray(),
            time(),
        );
        $workspace->zapomnitOperaciyu(Workspace::OPERATION_UPDATE, $workflowId, time());
        $this->workspaces->update($workspace);
    }

    /**
     * @param list<array{projectId?: mixed, branch?: mixed}> $projects
     *
     * @return list<array{projectId: string, branch: string}>
     */
    private function normalizeProjects(array $projects, Workspace $state): array
    {
        $existing = [];
        foreach ($state->getProjects() as $project) {
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
}
