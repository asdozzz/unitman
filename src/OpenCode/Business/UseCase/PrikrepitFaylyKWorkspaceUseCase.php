<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UseCase;

use App\OpenCode\Business\Command\PrikrepitFaylyKWorkspace;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\Model\Workspace;
use App\OpenCode\Business\Model\WorkspacePendingUpdate;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatFaylamiWorkspace;
use App\OpenCode\Business\Port\UmeetUpravlyatWorkspace;
use App\OpenCode\Business\Workspace\Model\WorkspaceFeatureFlags;
use App\OpenCode\Business\Workspace\UseCase\PrikrepitFaylyKWorkspaceSessiiUseCase;

/**
 * Прикрепление загруженных файлов к работающему workspace.
 */
final class PrikrepitFaylyKWorkspaceUseCase
{
    public function __construct(
        private readonly SessiyaOpenCodeRepository $sessionRepository,
        private readonly UmeetProveryatRekvizityOpenCode $credentials,
        private readonly UmeetUpravlyatFaylamiWorkspace $fayly,
        private readonly UmeetUpravlyatWorkspace $workspaceManager,
        private readonly int $workspaceMaxFiles = 20,
        private readonly ?WorkspaceFeatureFlags $flags = null,
        private readonly ?PrikrepitFaylyKWorkspaceSessiiUseCase $workspaceUseCase = null,
    ) {
    }

    public function handle(PrikrepitFaylyKWorkspace $command): void
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

        $fileIds = $this->normalizeFileIds($command->fileIds);

        if ($fileIds === []) {
            throw new \DomainException('opencode.workspace.file_not_found');
        }

        if (!$this->fayly->dostupniDlyaPrivyazki($fileIds, $accountId)) {
            throw new \DomainException('opencode.workspace.file_not_found');
        }

        $existing = $this->fayly->poluchitFayly($session->getId());
        $existingNames = [];
        foreach ($existing as $file) {
            $existingNames[$file->name] = true;
        }

        if (count($existing) + count($fileIds) > $this->workspaceMaxFiles) {
            throw new \DomainException('opencode.workspace.file_limit_exceeded');
        }

        $links = $this->fayly->poluchitSsylkiDlyaFaylov($fileIds);
        foreach ($fileIds as $fileId) {
            $name = $links[$fileId]['name'] ?? '';

            if ($name === '') {
                throw new \DomainException('opencode.workspace.file_not_found');
            }

            if (isset($existingNames[$name])) {
                throw new \DomainException('opencode.workspace.file_name_conflict');
            }

            $existingNames[$name] = true;
        }

        $attached = $this->fayly->prikrepit($session->getId(), $fileIds);
        $files = array_map(
            static fn ($file): array => ['fileId' => $file->fileId, 'name' => $file->name],
            $attached,
        );

        $pending = new WorkspacePendingUpdate(
            WorkspacePendingUpdate::KIND_FILES,
            [],
            $files,
            [],
            $workspace->isReadOnly(),
        );

        try {
            $workflowId = $this->workspaceManager->obnovit($session, $pending);
        } catch (\Throwable $error) {
            foreach ($attached as $file) {
                $this->fayly->udalitFayl($file->fileId);
            }

            throw $error;
        }

        $this->sessionRepository->saveWorkspaceUpdate($session->getId(), $workflowId, $pending);
        $workspace->zapomnitOperaciyu(Workspace::OPERATION_UPDATE, $workflowId, time());
        $this->sessionRepository->update($session);
    }

    /**
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

    private function trebovatWorkspace(SessiyaOpenCode $session): Workspace
    {
        $workspace = $session->getWorkspace();

        if (!$session->estWorkspace() || $workspace === null) {
            throw new \DomainException('opencode.workspace.not_found');
        }

        return $workspace;
    }
}
