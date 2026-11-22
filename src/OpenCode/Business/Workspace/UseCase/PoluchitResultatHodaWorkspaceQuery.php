<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Workspace\UseCase;

use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetVypolnyatHodOpenCode;
use App\OpenCode\Business\ReadModel\HodOpenCodeReadModel;
use App\OpenCode\Business\Workspace\Port\WorkspaceSessionRepository;
use App\OpenCode\Business\Workspace\Port\WorkspaceTurnRepository;
use App\OpenCode\Business\Workspace\Utils\ProverkaDostupaWorkspaceSessii;

final class PoluchitResultatHodaWorkspaceQuery
{
    public function __construct(
        private WorkspaceSessionRepository $workspaces,
        private WorkspaceTurnRepository $turns,
        private UmeetProveryatRekvizityOpenCode $credentials,
        private UmeetVypolnyatHodOpenCode $runner,
        private ProverkaDostupaWorkspaceSessii $dostup,
    ) {
    }

    public function handle(string $turnId): HodOpenCodeReadModel
    {
        $accountId = $this->credentials->tekuschiiAccountId();
        $turn = $this->turns->getById($turnId);
        $workspace = $this->workspaces->getById($turn->getSessionId());
        $this->dostup->proverit($workspace, $accountId);

        if ($turn->estVProzesse()) {
            $result = $this->runner->poluchitResultatHoda($turn->getWorkflowId());

            if ($result !== null) {
                $turn->primenitRezultat($result, time());
                $this->turns->update($turn);
            }
        }

        return HodOpenCodeReadModel::izModela(
            $turn,
            $this->runner->kanalProgressa($turn->getWorkflowId()),
        );
    }
}
