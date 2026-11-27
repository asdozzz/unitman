<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UseCase;

use App\OpenCode\Business\Command\SozdatWorkspace;
use App\OpenCode\Business\Model\ProektWorkspace;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Model\SessiyaOpenCodeFilter;
use App\OpenCode\Business\Model\TipSessiiOpenCode;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatFaylamiWorkspace;
use App\OpenCode\Business\Port\UmeetUpravlyatProektamiWorkspace;
use App\OpenCode\Business\Port\UmeetUpravlyatWorkspace;
use App\OpenCode\Business\Port\UuidGenerator;

/**
 * Создание workspace-сессии.
 *
 * Реквизиты и проекты проверяются до записи; имя уникально в рамках аккаунта.
 * Если Runner не принял запуск, сессия всё равно сохраняется в статусе ERROR —
 * пользователь получает id и видит причину в интерфейсе.
 */
final class SozdatWorkspaceUseCase
{
    private const BRANCH_MAX_LENGTH = 256;

    public function __construct(
        private SessiyaOpenCodeRepository $sessionRepository,
        private UmeetProveryatRekvizityOpenCode $credentials,
        private UmeetUpravlyatProektamiWorkspace $projectami,
        private UmeetUpravlyatWorkspace $workspaceManager,
        private UuidGenerator $uuidGenerator,
        private ?UmeetUpravlyatFaylamiWorkspace $fayly = null,
        private int $workspaceMaxFiles = 20,
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

        if (count($projectIds) > SessiyaOpenCode::MAX_WORKSPACE_PROJECTS) {
            throw new \DomainException('opencode.workspace.too_many_projects');
        }

        if (count($fileIds) > $this->workspaceMaxFiles) {
            throw new \DomainException('opencode.workspace.file_limit_exceeded');
        }

        if ($fileIds !== [] && ($this->fayly === null || !$this->fayly->dostupniDlyaPrivyazki($fileIds, $accountId))) {
            throw new \DomainException('opencode.workspace.file_not_found');
        }

        $this->proveritUnikalnostImeni($accountId, $name);

        $projects = $this->normalizeProjects(
            $this->projectami->poluchitProekti($projectIds, $accountId, $branch, $projectBranches),
            $projectBranches,
            $branch,
        );

        $session = SessiyaOpenCode::createWorkspace(
            $this->uuidGenerator->makeGuid(),
            $accountId,
            $credentialId,
            $name,
            $branch,
            $projects,
            $command->prompt,
            time(),
            $model === '' ? null : $model,
        );

        $this->sessionRepository->save($session);

        if ($fileIds !== [] && $this->fayly !== null) {
            $this->fayly->prikrepit($session->getId(), $fileIds);
        }

        try {
            $this->workspaceManager->zapustit($session);
        } catch (\Throwable) {
            $session->getWorkspace()?->otmetitOshibku('opencode.workspace.operation_failed', time());
            $this->sessionRepository->update($session);

            return $session->getId();
        }

        $this->sessionRepository->update($session);

        return $session->getId();
    }

    /**
     * Убирает пустые идентификаторы, сохраняет порядок; дубликат проекта —
     * ошибка (иначе в снимке оказался бы один и тот же проект дважды).
     *
     * @param list<string> $projectIds
     *
     * @return list<string>
     */
    /**
     * Убирает пустые идентификаторы и дубликаты файлов.
     *
     * @param list<string> $fileIds
     *
     * @return list<string>
     */
    private function normalizeFileIds(array $fileIds): array
    {
        $result = [];
        $seen = [];

        foreach ($fileIds as $fileId) {
            if (!is_string($fileId)) {
                continue;
            }

            $normalized = trim($fileId);

            if ($normalized === '' || isset($seen[$normalized])) {
                continue;
            }

            $seen[$normalized] = true;
            $result[] = $normalized;
        }

        return $result;
    }

    private function normalizeProjectIds(array $projectIds): array
    {
        $result = [];
        $seen = [];

        foreach ($projectIds as $projectId) {
            $normalized = trim($projectId);

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
     * Переопределения веток: проект обязан входить в `projectIds`, ветка —
     * быть непустой и не длиннее лимита, дубликат проекта запрещён.
     *
     * @param list<mixed>  $projectBranches сырые данные из тела запроса
     * @param list<string> $projectIds
     *
     * @return array<string, string> projectId => branch
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

            if (isset($result[$projectId])) {
                throw new \DomainException('opencode.workspace.project_conflict');
            }

            $branch = is_array($item) && is_string($item['branch'] ?? null)
                ? trim($item['branch'])
                : '';

            if ($branch === '') {
                throw new \DomainException('opencode.workspace.branch_required');
            }

            if (mb_strlen($branch) > self::BRANCH_MAX_LENGTH) {
                throw new \DomainException('opencode.workspace.branch_too_long');
            }

            $result[$projectId] = $branch;
        }

        return $result;
    }

    private function proveritUnikalnostImeni(string $accountId, string $name): void
    {
        $filter = new SessiyaOpenCodeFilter(false, null, TipSessiiOpenCode::WORKSPACE);

        foreach ($this->sessionRepository->findByAccountId($accountId, $filter) as $session) {
            if ($session->estWorkspace() && $session->getTitle() === $name) {
                throw new \DomainException('opencode.workspace.name_already_exists');
            }
        }
    }

    /**
     * Фиксирует фактическую ветку каждого проекта: переопределение из
     * `projectBranches`, иначе общая ветка workspace.
     *
     * @param list<ProektWorkspace>   $projects
     * @param array<string, string>   $projectBranches
     *
     * @return list<ProektWorkspace>
     */
    private function normalizeProjects(array $projects, array $projectBranches = [], string $commonBranch = ''): array
    {
        $result = [];

        foreach ($projects as $project) {
            $result[] = new ProektWorkspace(
                $project->projectId,
                $project->projectName,
                $project->projectCode,
                $project->canPush,
                count($result),
                $project->mainBranch,
                $projectBranches[$project->projectId] ?? $commonBranch,
            );
        }

        return $result;
    }
}
