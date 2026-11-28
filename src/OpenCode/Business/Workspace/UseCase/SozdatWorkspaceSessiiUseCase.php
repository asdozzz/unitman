<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Workspace\UseCase;

use App\OpenCode\Business\Command\SozdatWorkspace;
use App\OpenCode\Business\Model\ProektWorkspace;
use App\OpenCode\Business\Model\RolUchastnikaSessii;
use App\OpenCode\Business\Model\UchastnikSessii;
use App\OpenCode\Business\Model\Workspace;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatFaylamiWorkspace;
use App\OpenCode\Business\Port\UmeetUpravlyatProektamiWorkspace;
use App\OpenCode\Business\Port\UmeetUpravlyatWorkspace;
use App\OpenCode\Business\Port\UuidGenerator;
use App\OpenCode\Business\Workspace\Model\WorkspaceFilter;
use App\OpenCode\Business\Workspace\Model\WorkspaceSession;
use App\OpenCode\Business\Workspace\Port\WorkspaceParticipantsRepository;
use App\OpenCode\Business\Workspace\Port\WorkspaceSessionRepository;

/**
 * Создание workspace на самостоятельном агрегате.
 *
 * Контракт и коды ошибок совпадают с легаси-путём: имя уникально в рамках
 * аккаунта, реквизиты и проекты проверяются до записи. Первичная запись
 * включает проекты, участника-владельца, стартовый промпт и запуск стенда.
 */
final class SozdatWorkspaceSessiiUseCase
{
    public function __construct(
        private readonly WorkspaceSessionRepository $workspaces,
        private readonly WorkspaceParticipantsRepository $uchastniki,
        private readonly UmeetProveryatRekvizityOpenCode $credentials,
        private readonly UmeetUpravlyatProektamiWorkspace $projectami,
        private readonly UmeetUpravlyatWorkspace $workspaceManager,
        private readonly UuidGenerator $uuidGenerator,
        private readonly ?UmeetUpravlyatFaylamiWorkspace $fayly = null,
        private readonly int $workspaceMaxFiles = 20,
    ) {
    }

    public function handle(SozdatWorkspace $command): string
    {
        $accountId = $this->credentials->tekuschiiAccountId();
        $credentialId = trim($command->credentialId);

        $this->credentials->trebovatVladelca($accountId, $credentialId);

        $model = $this->credentials->razreshitModel($accountId, $credentialId, $command->model);

        $name = trim($command->name);
        $branch = trim($command->branch);
        $projectIds = $this->normalizeProjectIds($command->projectIds);
        $projectBranches = $this->normalizeProjectBranches($command->projectBranches, $projectIds);
        $fileIds = $this->normalizeFileIds($command->fileIds);

        if (count($projectIds) > Workspace::MAX_PROJECTS) {
            throw new \DomainException('opencode.workspace.too_many_projects');
        }

        if (count($fileIds) > $this->workspaceMaxFiles) {
            throw new \DomainException('opencode.workspace.file_limit_exceeded');
        }

        if ($fileIds !== [] && ($this->fayly === null || !$this->fayly->dostupniDlyaPrivyazki($fileIds, $accountId))) {
            throw new \DomainException('opencode.workspace.file_not_found');
        }

        $this->proveritUnikalnostImeni($accountId, $name);

        $projects = $this->normalizeProjects($projectBranches, $branch, $projectIds, $accountId);

        $now = time();
        $id = $this->uuidGenerator->makeGuid();

        $workspace = WorkspaceSession::create(
            $id,
            $accountId,
            $credentialId,
            $name,
            $branch,
            $projects,
            $command->prompt,
            $now,
            $model === '' ? null : $model,
        );

        $this->workspaces->save($workspace);
        $this->uchastniki->save(UchastnikSessii::create(
            $id,
            $accountId,
            RolUchastnikaSessii::OWNER,
            $credentialId,
            $model === '' ? null : $model,
            null,
            $now,
        ));

        if ($fileIds !== [] && $this->fayly !== null) {
            $this->fayly->prikrepit($id, $fileIds);
        }

        try {
            $this->workspaceManager->zapustit($workspace);
            $this->workspaces->update($workspace);
        } catch (\Throwable) {
            $workspace->otmetitOshibku('opencode.workspace.operation_failed', time());
            $this->workspaces->update($workspace);
        }

        return $id;
    }

    private function proveritUnikalnostImeni(string $accountId, string $name): void
    {
        foreach ($this->workspaces->findByAccountId($accountId, new WorkspaceFilter()) as $existing) {
            if ($existing->getName() === $name) {
                throw new \DomainException('opencode.workspace.name_already_exists');
            }
        }
    }

    /**
     * @param array<string, string> $projectBranches
     * @param list<string> $projectIds
     *
     * @return list<ProektWorkspace>
     */
    private function normalizeProjects(array $projectBranches, string $branch, array $projectIds, string $accountId): array
    {
        $snapshots = $this->projectami->poluchitProekti($projectIds, $accountId, $branch, $projectBranches);
        $result = [];

        foreach ($snapshots as $snapshot) {
            $result[] = new ProektWorkspace(
                $snapshot->projectId,
                $snapshot->projectName,
                $snapshot->projectCode,
                $snapshot->canPush,
                count($result),
                $snapshot->mainBranch,
                $projectBranches[$snapshot->projectId] ?? $branch,
            );
        }

        return $result;
    }

    /**
     * @param list<mixed> $projectIds
     *
     * @return list<string>
     */
    private function normalizeProjectIds(array $projectIds): array
    {
        $result = [];
        $seen = [];

        foreach ($projectIds as $projectId) {
            $normalized = trim((string) $projectId);

            if ($normalized === '') {
                continue;
            }

            if (isset($seen[$normalized])) {
                throw new \DomainException('opencode.workspace.project_conflict');
            }

            $seen[$normalized] = true;
            $result[] = $normalized;
        }

        return $result;
    }

    /**
     * @param list<mixed> $projectBranches
     * @param list<string> $projectIds
     *
     * @return array<string, string>
     */
    private function normalizeProjectBranches(array $projectBranches, array $projectIds): array
    {
        $dostupnie = array_fill_keys($projectIds, true);
        $result = [];

        foreach ($projectBranches as $item) {
            $projectId = is_array($item) && is_string($item['projectId'] ?? null)
                ? trim($item['projectId'])
                : '';

            if ($projectId === '' || !isset($dostupnie[$projectId])) {
                throw new \DomainException('opencode.workspace.project_not_found');
            }

            $branch = is_array($item) && is_string($item['branch'] ?? null)
                ? trim($item['branch'])
                : '';

            if ($branch === '') {
                throw new \DomainException('opencode.workspace.branch_required');
            }

            $result[$projectId] = $branch;
        }

        return $result;
    }

    /**
     * @param list<mixed> $fileIds
     *
     * @return list<string>
     */
    private function normalizeFileIds(array $fileIds): array
    {
        $result = [];
        $seen = [];

        foreach ($fileIds as $fileId) {
            $normalized = is_string($fileId) ? trim($fileId) : '';

            if ($normalized === '' || isset($seen[$normalized])) {
                continue;
            }

            $seen[$normalized] = true;
            $result[] = $normalized;
        }

        return $result;
    }
}
