<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Workspace\UseCase;

use App\OpenCode\Business\Model\ProektWorkspace;
use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Workspace\Port\WorkspaceSessionRepository;
use App\OpenCode\Business\Workspace\Port\WorkspaceTurnRepository;
use App\OpenCode\Business\Workspace\Utils\ProverkaDostupaWorkspaceSessii;

final class PoluchitSostoyanieWorkspaceSessiiQuery
{
    public function __construct(
        private WorkspaceSessionRepository $workspaces,
        private WorkspaceTurnRepository $turns,
        private UmeetProveryatRekvizityOpenCode $credentials,
        private ProverkaDostupaWorkspaceSessii $dostup,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(string $workspaceId): array
    {
        $accountId = $this->credentials->tekuschiiAccountId();
        $workspace = $this->workspaces->getById($workspaceId);
        $this->dostup->proverit($workspace, $accountId);

        $state = $workspace->getWorkspace();
        $poslednii = $this->turns->findPosledniiPoSessii($workspaceId);
        $canAccept = !$workspace->estArhiv()
            && $state->getStatus() === StatusWorkspace::RUNNING
            && ($poslednii === null || $poslednii->getStatus()->value !== 'RUNNING');

        return [
            'workspaceId' => $workspace->getId(),
            'status' => $state->getStatus()->value,
            'archived' => $workspace->estArhiv(),
            'archiveReason' => $workspace->getArchiveReason(),
            'canAcceptMessages' => $canAccept,
            'readOnly' => $state->isReadOnly(),
            'projects' => array_map(
                static fn (ProektWorkspace $project): array => [
                    'projectId' => $project->projectId,
                    'projectName' => $project->projectName,
                    'projectCode' => $project->projectCode,
                    'canPush' => $project->canPush,
                    'positionIndex' => $project->positionIndex,
                    'branch' => $project->branch,
                ],
                $state->getProjects(),
            ),
            'lastTurn' => $poslednii?->toArray(),
        ];
    }
}
