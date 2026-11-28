<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Workspace\UseCase;

use App\OpenCode\Business\Command\UdalitWorkspace;
use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatWorkspace;
use App\OpenCode\Business\Workspace\Port\WorkspaceSessionRepository;

/**
 * Удаление workspace на самостоятельном агрегате.
 *
 * Ставит DELETING атомарно и запускает финальную операцию Runner. Записи
 * удаляются после успешного завершения операции; под флагом WRITE=1 это
 * делает фоновая сверка/откат, легаси-строки не создаются.
 */
final class UdalitWorkspaceSessiiUseCase
{
    private const DELETE_FROM = [
        StatusWorkspace::RUNNING,
        StatusWorkspace::ARCHIVED,
        StatusWorkspace::ERROR,
    ];

    public function __construct(
        private readonly WorkspaceSessionRepository $workspaces,
        private readonly UmeetProveryatRekvizityOpenCode $credentials,
        private readonly UmeetUpravlyatWorkspace $workspaceManager,
    ) {
    }

    public function handle(UdalitWorkspace $command): void
    {
        $accountId = $this->credentials->tekuschiiAccountId();
        $workspaceId = trim($command->workspaceId);

        if ($workspaceId === '') {
            throw new \DomainException('opencode.workspace.not_found');
        }

        $workspace = $this->workspaces->getById($workspaceId);
        $workspace->trebovatVladelca($accountId);

        if ($workspace->getWorkspace()->getStatus() === StatusWorkspace::DELETING) {
            return;
        }

        if (!in_array($workspace->getWorkspace()->getStatus(), self::DELETE_FROM, true)) {
            throw new \DomainException('opencode.workspace.not_running');
        }

        if (!$this->workspaces->perevestiStatus($workspaceId, self::DELETE_FROM, StatusWorkspace::DELETING)) {
            $fresh = $this->workspaces->getById($workspaceId);

            if ($fresh->getWorkspace()->getStatus() === StatusWorkspace::DELETING) {
                return;
            }

            throw new \DomainException('opencode.workspace.operation_failed');
        }

        $fresh = $this->workspaces->getById($workspaceId);

        try {
            $this->workspaceManager->udalit($fresh);
            $this->workspaces->update($fresh);
        } catch (\Throwable $error) {
            $this->workspaces->perevestiStatus(
                $workspaceId,
                [StatusWorkspace::DELETING],
                StatusWorkspace::ERROR,
            );

            $failed = $this->workspaces->getById($workspaceId);
            $failed->otmetitOshibku('opencode.workspace.operation_failed', time());
            $this->workspaces->update($failed);

            throw $error;
        }
    }
}
