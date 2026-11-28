<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Workspace\UseCase;

use App\OpenCode\Business\Command\PoluchitSsylkuWorkspace;
use App\OpenCode\Business\Model\ProektWorkspace;
use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatProektamiWorkspace;
use App\OpenCode\Business\ReadModel\WorkspaceLinkHolder;
use App\OpenCode\Business\Utils\SlugWorkspace;
use App\OpenCode\Business\Workspace\Port\WorkspaceSessionRepository;
use App\OpenCode\Business\Workspace\Utils\ProverkaDostupaWorkspaceSessii;

/**
 * Ссылка на произвольный порт workspace-контейнера на новом агрегате.
 *
 * Доступна любому участнику (owner/writer/viewer): ACL Unitman сам решает,
 * будет адрес read-only или с редактированием.
 */
final class PoluchitSsylkuWorkspaceSessiiQuery
{
    public function __construct(
        private readonly WorkspaceSessionRepository $workspaces,
        private readonly UmeetProveryatRekvizityOpenCode $credentials,
        private readonly UmeetUpravlyatProektamiWorkspace $projectami,
        private readonly ProverkaDostupaWorkspaceSessii $dostup,
    ) {
    }

    public function handle(PoluchitSsylkuWorkspace $command): WorkspaceLinkHolder
    {
        $accountId = $this->credentials->tekuschiiAccountId();
        $workspaceId = trim($command->workspaceId);

        if ($workspaceId === '') {
            throw new \DomainException('opencode.workspace.not_found');
        }

        $workspace = $this->workspaces->getById($workspaceId);

        if ($this->dostup->rol($workspace, $accountId) === null) {
            throw new \DomainException('opencode.session.not_found');
        }

        $state = $workspace->getWorkspace();

        if ($state->getStatus() !== StatusWorkspace::RUNNING) {
            throw new \DomainException('opencode.workspace.not_running');
        }

        if ($command->port < 1 || $command->port > 65535) {
            throw new \DomainException('opencode.workspace.invalid_port');
        }

        $first = $state->getProjects()[0] ?? null;

        if (!$first instanceof ProektWorkspace) {
            throw new \DomainException('opencode.workspace.not_found');
        }

        $link = $this->projectami->poluchitSsylkuPoPortu(
            $first->projectId,
            $accountId,
            SlugWorkspace::dlya($workspace->getName(), $workspace->getId()),
            $command->port,
            $command->type,
            $command->suffix,
        );

        if ($link === null) {
            throw new \DomainException('opencode.workspace.link_unavailable');
        }

        return new WorkspaceLinkHolder($link);
    }
}
