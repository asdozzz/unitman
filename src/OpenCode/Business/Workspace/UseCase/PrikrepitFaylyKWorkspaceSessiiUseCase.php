<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Workspace\UseCase;

use App\OpenCode\Business\Command\PrikrepitFaylyKWorkspace;
use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\Model\Workspace;
use App\OpenCode\Business\Model\WorkspacePendingUpdate;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatFaylamiWorkspace;
use App\OpenCode\Business\Port\UmeetUpravlyatWorkspace;
use App\OpenCode\Business\Workspace\Port\WorkspaceSessionRepository;

/**
 * Прикрепление файлов к работающему workspace на новом агрегате.
 */
final class PrikrepitFaylyKWorkspaceSessiiUseCase
{
    public function __construct(
        private readonly WorkspaceSessionRepository $workspaces,
        private readonly UmeetProveryatRekvizityOpenCode $credentials,
        private readonly UmeetUpravlyatFaylamiWorkspace $fayly,
        private readonly UmeetUpravlyatWorkspace $workspaceManager,
        private readonly int $workspaceMaxFiles = 20,
    ) {
    }

    public function handle(PrikrepitFaylyKWorkspace $command): void
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

        $fileIds = $this->normalizeFileIds($command->fileIds);

        if ($fileIds === []) {
            throw new \DomainException('opencode.workspace.file_not_found');
        }

        if (!$this->fayly->dostupniDlyaPrivyazki($fileIds, $accountId)) {
            throw new \DomainException('opencode.workspace.file_not_found');
        }

        $existing = $this->fayly->poluchitFayly($workspace->getId());
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

        $attached = $this->fayly->prikrepit($workspace->getId(), $fileIds);
        $files = array_map(
            static fn ($file): array => ['fileId' => $file->fileId, 'name' => $file->name],
            $attached,
        );

        $pending = new WorkspacePendingUpdate(
            WorkspacePendingUpdate::KIND_FILES,
            [],
            $files,
            [],
            $state->isReadOnly(),
        );

        try {
            $workflowId = $this->workspaceManager->obnovit($workspace, $pending);
        } catch (\Throwable $error) {
            foreach ($attached as $file) {
                $this->fayly->udalitFayl($file->fileId);
            }

            throw $error;
        }

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
}
