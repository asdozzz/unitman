<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UseCase;

use App\OpenCode\Business\Command\UdalitFaylWorkspace;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\Model\Workspace;
use App\OpenCode\Business\Model\WorkspacePendingUpdate;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetUpravlyatFaylamiWorkspace;
use App\OpenCode\Business\Port\UmeetUpravlyatWorkspace;

/**
 * Удаление файла workspace.
 *
 * Метаданные удаляются только после успешного удаления файла из контейнера.
 */
final class UdalitFaylWorkspaceUseCase
{
    public function __construct(
        private readonly SessiyaOpenCodeRepository $sessionRepository,
        private readonly UmeetUpravlyatFaylamiWorkspace $fayly,
        private readonly UmeetUpravlyatWorkspace $workspaceManager,
    ) {
    }

    public function handle(UdalitFaylWorkspace $command): void
    {
        $session = $this->sessionRepository->getById(trim($command->workspaceId));
        $workspace = $this->trebovatWorkspace($session);
        $fileId = trim($command->fileId);

        if ($fileId === '') {
            throw new \DomainException('mediateka.file.not_found');
        }

        if ($workspace->getStatus() !== StatusWorkspace::RUNNING) {
            throw new \DomainException('opencode.workspace.not_running');
        }

        if ($this->sessionRepository->findWorkspaceUpdate($session->getId()) !== null) {
            throw new \DomainException('opencode.workspace.update_in_progress');
        }

        $target = null;
        foreach ($this->fayly->poluchitFayly($session->getId()) as $file) {
            if ($file->fileId === $fileId) {
                $target = $file;
                break;
            }
        }

        if ($target === null) {
            throw new \DomainException('mediateka.file.not_found');
        }

        $pending = new WorkspacePendingUpdate(
            WorkspacePendingUpdate::KIND_FILES_DELETE,
            [],
            [],
            [['fileId' => $target->fileId, 'name' => $target->name]],
            $workspace->isReadOnly(),
        );

        $workflowId = $this->workspaceManager->obnovit($session, $pending);

        $this->sessionRepository->saveWorkspaceUpdate($session->getId(), $workflowId, $pending);
        $workspace->zapomnitOperaciyu(Workspace::OPERATION_UPDATE, $workflowId, time());
        $this->sessionRepository->update($session);
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
