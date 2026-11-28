<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Workspace\UseCase;

use App\OpenCode\Business\Command\ArhivirovatWorkspace;
use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatWorkspace;
use App\OpenCode\Business\ReadModel\RezultatArhivaciiWorkspace;
use App\OpenCode\Business\Workspace\Model\WorkspaceSession;
use App\OpenCode\Business\Workspace\Port\WorkspaceSessionRepository;

/**
 * Архивация workspace на самостоятельном агрегате.
 *
 * Семантика и коды ошибок совпадают с легаси: проверка незакоммиченных
 * изменений, атомарный переход в ARCHIVING и запуск остановки стенда.
 */
final class ArhivirovatWorkspaceSessiiUseCase
{
    private const ARCHIVE_FROM = [StatusWorkspace::RUNNING, StatusWorkspace::ERROR];

    public function __construct(
        private readonly WorkspaceSessionRepository $workspaces,
        private readonly UmeetProveryatRekvizityOpenCode $credentials,
        private readonly UmeetUpravlyatWorkspace $workspaceManager,
    ) {
    }

    public function handle(ArhivirovatWorkspace $command): RezultatArhivaciiWorkspace
    {
        $accountId = $this->credentials->tekuschiiAccountId();
        $workspaceId = trim($command->workspaceId);

        if ($workspaceId === '') {
            throw new \DomainException('opencode.workspace.not_found');
        }

        $workspace = $this->workspaces->getById($workspaceId);
        $workspace->trebovatVladelca($accountId);

        $status = $workspace->getWorkspace()->getStatus();

        if ($status === StatusWorkspace::ARCHIVED || $status === StatusWorkspace::ARCHIVING) {
            return new RezultatArhivaciiWorkspace(false, [], $status->value);
        }

        if ($status === StatusWorkspace::DELETING) {
            throw new \DomainException('opencode.workspace.delete_in_progress');
        }

        if (!in_array($status, self::ARCHIVE_FROM, true)) {
            throw new \DomainException('opencode.workspace.not_running');
        }

        if (!$command->force) {
            $check = $this->workspaceManager->poluchitIzmeneniya($workspace);

            if (!$check->success) {
                throw new \DomainException('opencode.workspace.operation_failed');
            }

            $dirty = $this->dirtyProjects($check->projects);

            if ($dirty !== []) {
                return new RezultatArhivaciiWorkspace(true, $dirty, $status->value);
            }
        }

        if (!$this->workspaces->perevestiStatus($workspaceId, self::ARCHIVE_FROM, StatusWorkspace::ARCHIVING)) {
            $fresh = $this->workspaces->getById($workspaceId);

            return new RezultatArhivaciiWorkspace(
                false,
                [],
                $fresh->getWorkspace()->getStatus()->value,
            );
        }

        $fresh = $this->workspaces->getById($workspaceId);
        $this->workspaceManager->ostanovit($fresh);
        $this->workspaces->update($fresh);

        return new RezultatArhivaciiWorkspace(false, [], StatusWorkspace::ARCHIVING->value);
    }

    /**
     * @param list<array{projectId: string, name: string, dirty: bool, files: list<string>}> $projects
     *
     * @return list<array{projectId: string, name: string, files: list<string>}>
     */
    private function dirtyProjects(array $projects): array
    {
        $dirty = [];

        foreach ($projects as $project) {
            if ($project['dirty']) {
                $dirty[] = [
                    'projectId' => $project['projectId'],
                    'name' => $project['name'],
                    'files' => $project['files'],
                ];
            }
        }

        return $dirty;
    }
}
