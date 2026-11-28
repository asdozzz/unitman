<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Workspace\UseCase;

use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetVypolnyatHodOpenCode;
use App\OpenCode\Business\ReadModel\HodOpenCodeReadModel;
use App\OpenCode\Business\Workspace\Port\WorkspaceSessionRepository;
use App\OpenCode\Business\Workspace\Port\WorkspaceTurnRepository;
use App\OpenCode\Business\Workspace\Utils\ProverkaDostupaWorkspaceSessii;
use App\OpenCode\Business\Workspace\Utils\ZavershenieHodaWorkspace;

final class PoluchitResultatHodaWorkspaceQuery
{
    public function __construct(
        private WorkspaceSessionRepository $workspaces,
        private WorkspaceTurnRepository $turns,
        private UmeetProveryatRekvizityOpenCode $credentials,
        private UmeetVypolnyatHodOpenCode $runner,
        private ProverkaDostupaWorkspaceSessii $dostup,
        private ZavershenieHodaWorkspace $zavershenie,
    ) {
    }

    public function handle(string $turnId): HodOpenCodeReadModel
    {
        $accountId = $this->credentials->tekuschiiAccountId();
        $turn = $this->turns->getById($turnId);
        $workspace = $this->workspaces->getById($turn->getSessionId());
        $this->dostup->proverit($workspace, $accountId);

        $turn = $this->zavershenie->zavershit($turn);

        return HodOpenCodeReadModel::izModela(
            $turn,
            $this->runner->kanalProgressa($turn->getWorkflowId()),
        );
    }
}
