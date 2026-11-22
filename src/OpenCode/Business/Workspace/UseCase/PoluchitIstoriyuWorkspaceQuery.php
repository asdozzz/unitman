<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Workspace\UseCase;

use App\OpenCode\Business\Model\HodOpenCode;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetVypolnyatHodOpenCode;
use App\OpenCode\Business\ReadModel\HodOpenCodeReadModel;
use App\OpenCode\Business\Workspace\Port\WorkspaceSessionRepository;
use App\OpenCode\Business\Workspace\Port\WorkspaceTurnRepository;
use App\OpenCode\Business\Workspace\Utils\ProverkaDostupaWorkspaceSessii;

final class PoluchitIstoriyuWorkspaceQuery
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
     * @return list<HodOpenCodeReadModel>
     */
    public function handle(string $workspaceId): array
    {
        $accountId = $this->credentials->tekuschiiAccountId();
        $workspace = $this->workspaces->getById($workspaceId);
        $this->dostup->proverit($workspace, $accountId);

        return array_map(
            fn (HodOpenCode $turn): HodOpenCodeReadModel => HodOpenCodeReadModel::izModela(
                $turn,
                $this->runner->kanalProgressa($turn->getWorkflowId()),
            ),
            $this->turns->findBySessionId($workspaceId),
        );
    }
}
