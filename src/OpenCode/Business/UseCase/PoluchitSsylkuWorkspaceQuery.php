<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UseCase;

use App\OpenCode\Business\Command\PoluchitSsylkuWorkspace;
use App\OpenCode\Business\Model\ProektWorkspace;
use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatProektamiWorkspace;
use App\OpenCode\Business\ReadModel\WorkspaceLinkHolder;
use App\OpenCode\Business\Utils\SlugWorkspace;

/**
 * Ссылка на произвольный порт workspace-контейнера.
 *
 * Повторяет паттерн `PoluchitSsylkuPoPomoschniku`: владелец workspace
 * проверяется по текущему аккаунту, ссылка строится по общему правилу
 * `{scheme}://{port}.{workspaceSlug}.workspaces.{host}`.
 */
final class PoluchitSsylkuWorkspaceQuery
{
    public function __construct(
        private SessiyaOpenCodeRepository $sessionRepository,
        private UmeetProveryatRekvizityOpenCode $credentials,
        private UmeetUpravlyatProektamiWorkspace $projectami,
    ) {
    }

    /**
     * @throws \DomainException
     *   `opencode.workspace.not_found` — workspace не найден или чужой,
     *   `opencode.workspace.not_running` — статус не RUNNING,
     *   `opencode.workspace.invalid_port` — порт вне 1–65535,
     *   `opencode.workspace.link_unavailable` — публичный адрес раннера не задан
     */
    public function handle(PoluchitSsylkuWorkspace $command): WorkspaceLinkHolder
    {
        $accountId = $this->credentials->tekuschiiAccountId();
        $workspaceId = trim($command->workspaceId);

        if ($workspaceId === '') {
            throw new \DomainException('opencode.workspace.not_found');
        }

        $session = $this->sessionRepository->getById($workspaceId);
        $session->trebovatVladelca($accountId);

        $workspace = $session->getWorkspace();

        if (!$session->estWorkspace() || $workspace === null) {
            throw new \DomainException('opencode.workspace.not_found');
        }

        if ($workspace->getStatus() !== StatusWorkspace::RUNNING) {
            throw new \DomainException('opencode.workspace.not_running');
        }

        if ($command->port < 1 || $command->port > 65535) {
            throw new \DomainException('opencode.workspace.invalid_port');
        }

        $first = $workspace->getProjects()[0] ?? null;

        if (!$first instanceof ProektWorkspace) {
            throw new \DomainException('opencode.workspace.not_found');
        }

        $link = $this->projectami->poluchitSsylkuPoPortu(
            $first->projectId,
            $accountId,
            SlugWorkspace::dlya($session->getTitle(), $session->getId()),
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
