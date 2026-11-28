<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UseCase;

use App\OpenCode\Business\Port\UmeetPoluchatNavyki;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\UnitSession\Port\UnitSessionRepository;
use App\OpenCode\Business\UnitSession\Utils\ProverkaDostupaKSessiiUnita;
use App\OpenCode\Business\Workspace\Port\WorkspaceSessionRepository;
use App\OpenCode\Business\Workspace\Utils\ProverkaDostupaWorkspaceSessii;

/**
 * Эффективные навыки для сессии OpenCode.
 *
 * Проекты разрешаются на сервере: для unit-сессии — проект её юнита, для
 * workspace-сессии — снимок проектов workspace. Доступ к сессии проверяется
 * существующей политикой; клиент не может запросить навыки чужого проекта.
 */
final class PoluchitNavykiSessiiQuery
{
    public function __construct(
        private readonly UnitSessionRepository $unitSessions,
        private readonly WorkspaceSessionRepository $workspaceSessions,
        private readonly ProverkaDostupaKSessiiUnita $unitAccess,
        private readonly ProverkaDostupaWorkspaceSessii $workspaceAccess,
        private readonly UmeetProveryatRekvizityOpenCode $account,
        private readonly UmeetPoluchatNavyki $navyki,
    ) {
    }

    /**
     * @return array{
     *     skills: list<array<string, mixed>>,
     *     manifestHash: string,
     *     totalBytes: int,
     *     truncated: bool
     * }
     */
    public function handle(string $sessionId): array
    {
        $accountId = $this->account->tekuschiiAccountId();

        $unitSession = $this->unitSessions->findById($sessionId);

        if ($unitSession !== null) {
            $this->unitAccess->proverit($unitSession, $accountId);

            return $this->navyki->effektivnyeNavykiDlyaProektov([$unitSession->getUnitProjectId()]);
        }

        $workspaceSession = $this->workspaceSessions->findById($sessionId);

        if ($workspaceSession !== null) {
            $this->workspaceAccess->proverit($workspaceSession, $accountId);

            return $this->navyki->effektivnyeNavykiDlyaProektov($workspaceSession->getProjectIds());
        }

        throw new \DomainException('opencode.session.not_found');
    }
}
