<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UseCase;

use App\OpenCode\Business\Command\ArhivirovatWorkspace;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatWorkspace;
use App\OpenCode\Business\ReadModel\RezultatArhivaciiWorkspace;

/**
 * Архивация workspace.
 *
 * Без `force` сначала проверяются незакоммиченные изменения: при их наличии
 * возвращается `requiresConfirmation = true` и workflow не запускается.
 * Переход статуса атомарный, поэтому повторный/параллельный запрос не
 * запустит вторую остановку контейнера.
 */
final class ArhivirovatWorkspaceUseCase
{
    /** Статусы, из которых допустим старт архивации. */
    private const ARCHIVE_FROM = [StatusWorkspace::RUNNING, StatusWorkspace::ERROR];

    public function __construct(
        private SessiyaOpenCodeRepository $sessionRepository,
        private UmeetProveryatRekvizityOpenCode $credentials,
        private UmeetUpravlyatWorkspace $workspaceManager,
    ) {
    }

    /**
     * @throws \DomainException если workspace не найден, удаляется или ещё не запущен
     */
    public function handle(ArhivirovatWorkspace $command): RezultatArhivaciiWorkspace
    {
        $accountId = $this->credentials->tekuschiiAccountId();
        $workspaceId = trim($command->workspaceId);

        if ($workspaceId === '') {
            throw new \DomainException('opencode.workspace.not_found');
        }

        $session = $this->sessionRepository->getById($workspaceId);
        $session->trebovatVladelca($accountId);

        $workspace = $session->getWorkspace();

        if (!$session->estWorkspace() || $workspace === null) {
            throw new \DomainException('opencode.workspace.not_found');
        }

        $status = $workspace->getStatus();

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
            $check = $this->workspaceManager->poluchitIzmeneniya($session);

            if (!$check->success) {
                throw new \DomainException('opencode.workspace.operation_failed');
            }

            $dirty = $this->dirtyProjects($check->projects);

            if ($dirty !== []) {
                return new RezultatArhivaciiWorkspace(true, $dirty, $status->value);
            }
        }

        if (!$this->sessionRepository->perevestiStatusWorkspace(
            $workspaceId,
            self::ARCHIVE_FROM,
            StatusWorkspace::ARCHIVING,
        )) {
            $fresh = $this->sessionRepository->getById($workspaceId);

            return new RezultatArhivaciiWorkspace(
                false,
                [],
                $fresh->getWorkspace()?->getStatus()->value ?? $status->value,
            );
        }

        $fresh = $this->sessionRepository->getById($workspaceId);
        $this->workspaceManager->ostanovit($fresh);
        $this->sessionRepository->update($fresh);

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
            if (!$project['dirty']) {
                continue;
            }

            $dirty[] = [
                'projectId' => $project['projectId'],
                'name' => $project['name'],
                'files' => $project['files'],
            ];
        }

        return $dirty;
    }
}
