<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UseCase;

use App\OpenCode\Business\Command\PoluchitSpisokWorkspace;
use App\OpenCode\Business\Model\ProektWorkspace;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Model\SessiyaOpenCodeFilter;
use App\OpenCode\Business\Model\TipSessiiOpenCode;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatProektamiWorkspace;
use App\OpenCode\Business\ReadModel\WorkspaceReadModel;
use App\OpenCode\Business\Utils\SlugWorkspace;

/**
 * Workspace-сессии текущего пользователя с фильтрами `onlyActive` и `search`.
 */
final class PoluchitSpisokWorkspaceQuery
{
    public function __construct(
        private SessiyaOpenCodeRepository $sessionRepository,
        private UmeetProveryatRekvizityOpenCode $credentials,
        private UmeetUpravlyatProektamiWorkspace $projectami,
    ) {
    }

    /**
     * @return list<WorkspaceReadModel>
     */
    public function handle(PoluchitSpisokWorkspace $query): array
    {
        $accountId = $this->credentials->tekuschiiAccountId();
        $filter = new SessiyaOpenCodeFilter(
            $query->onlyActive,
            $query->search,
            TipSessiiOpenCode::WORKSPACE,
        );

        $result = [];

        foreach ($this->sessionRepository->findByAccountId($accountId, $filter) as $session) {
            if (!$session->estWorkspace()) {
                continue;
            }

            $result[] = WorkspaceReadModel::izModela(
                $session,
                $this->ideLink($session, $accountId),
            );
        }

        return $result;
    }

    private function ideLink(SessiyaOpenCode $session, string $accountId): ?string
    {
        $first = $session->getWorkspace()?->getProjects()[0] ?? null;

        if (!$first instanceof ProektWorkspace) {
            return null;
        }

        try {
            return $this->projectami->poluchitSsylkuRedaktora(
                $first->projectId,
                $accountId,
                SlugWorkspace::dlya($session->getTitle(), $session->getId()),
            );
        } catch (\Throwable) {
            return null;
        }
    }
}
