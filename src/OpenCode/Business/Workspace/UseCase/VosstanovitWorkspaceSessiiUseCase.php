<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Workspace\UseCase;

use App\OpenCode\Business\Command\VosstanovitWorkspace;
use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatWorkspace;
use App\OpenCode\Business\Workspace\Port\WorkspaceSessionRepository;

/**
 * Восстановление workspace на самостоятельном агрегате.
 */
final class VosstanovitWorkspaceSessiiUseCase
{
    private const RESTORE_FROM = [StatusWorkspace::ARCHIVED, StatusWorkspace::ERROR];

    public function __construct(
        private readonly WorkspaceSessionRepository $workspaces,
        private readonly UmeetProveryatRekvizityOpenCode $credentials,
        private readonly UmeetUpravlyatWorkspace $workspaceManager,
    ) {
    }

    public function handle(VosstanovitWorkspace $command): void
    {
        $accountId = $this->credentials->tekuschiiAccountId();
        $workspaceId = trim($command->workspaceId);

        if ($workspaceId === '') {
            throw new \DomainException('opencode.workspace.not_found');
        }

        $workspace = $this->workspaces->getById($workspaceId);
        $workspace->trebovatVladelca($accountId);

        if ($workspace->getWorkspace()->getStatus() === StatusWorkspace::STARTING) {
            return;
        }

        if (!in_array($workspace->getWorkspace()->getStatus(), self::RESTORE_FROM, true)) {
            throw new \DomainException('opencode.workspace.not_running');
        }

        if (!$this->workspaces->perevestiStatus($workspaceId, self::RESTORE_FROM, StatusWorkspace::STARTING)) {
            $fresh = $this->workspaces->getById($workspaceId);

            if ($fresh->getWorkspace()->getStatus() === StatusWorkspace::STARTING) {
                return;
            }

            throw new \DomainException('opencode.workspace.operation_failed');
        }

        $fresh = $this->workspaces->getById($workspaceId);
        $this->workspaceManager->vosstanovit($fresh);
        $this->workspaces->update($fresh);
    }
}
