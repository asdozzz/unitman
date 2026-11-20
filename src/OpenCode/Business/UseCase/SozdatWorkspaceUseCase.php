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
    public function __construct(
        private SessiyaOpenCodeRepository $sessionRepository,
        private UmeetProveryatRekvizityOpenCode $credentials,
        private UmeetUpravlyatProektamiWorkspace $projectami,
        private UmeetUpravlyatWorkspace $workspaceManager,
        private UuidGenerator $uuidGenerator,
    ) {
    }

    public function handle(SozdatWorkspace $command): string
    {
        $accountId = $this->credentials->tekuschiiAccountId();
        $credentialId = trim($command->credentialId);

        $this->credentials->trebovatVladelca($accountId, $credentialId);

        $name = trim($command->name);
        $branch = trim($command->branch);
        $projectIds = $this->normalizeProjectIds($command->projectIds);

        if (count($projectIds) > SessiyaOpenCode::MAX_WORKSPACE_PROJECTS) {
            throw new \DomainException('opencode.workspace.too_many_projects');
        }

        $this->proveritUnikalnostImeni($accountId, $name);

        $projects = $this->normalizeProjects(
            $this->projectami->poluchitProekti($projectIds, $accountId),
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
        );

        $this->sessionRepository->save($session);

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
     * @param list<ProektWorkspace> $projects
     *
     * @return list<ProektWorkspace>
     */
    private function normalizeProjects(array $projects): array
    {
        $result = [];

        foreach ($projects as $project) {
            $result[] = new ProektWorkspace(
                $project->projectId,
                $project->projectName,
                $project->projectCode,
                $project->canPush,
                count($result),
            );
        }

        return $result;
    }
}
