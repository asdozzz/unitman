<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Workspace\UseCase;

use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetVypolnyatHodOpenCode;
use App\OpenCode\Business\Workspace\Port\WorkspaceSessionRepository;
use App\OpenCode\Business\Workspace\Port\WorkspaceTurnRepository;
use App\OpenCode\Business\Workspace\Utils\ProverkaDostupaWorkspaceSessii;

final class PoluchitProgressHodaWorkspaceQuery
{
    public function __construct(
        private WorkspaceSessionRepository $workspaces,
        private WorkspaceTurnRepository $turns,
        private UmeetProveryatRekvizityOpenCode $credentials,
        private UmeetVypolnyatHodOpenCode $runner,
        private ProverkaDostupaWorkspaceSessii $dostup,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function handle(string $turnId): ?array
    {
        $accountId = $this->credentials->tekuschiiAccountId();
        $turn = $this->turns->getById($turnId);
        $workspace = $this->workspaces->getById($turn->getSessionId());
        $this->dostup->proverit($workspace, $accountId);

        return $this->runner->poluchitProgressHoda($turn->getWorkflowId())?->toArray();
    }
}
