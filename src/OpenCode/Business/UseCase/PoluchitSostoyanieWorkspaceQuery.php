<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UseCase;

use App\OpenCode\Business\Command\PoluchitSostoyanieWorkspace;
use App\OpenCode\Business\Model\HodOpenCode;
use App\OpenCode\Business\Model\ProektWorkspace;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\Port\HodOpenCodeRepository;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatProektamiWorkspace;
use App\OpenCode\Business\ReadModel\ProektWorkspaceReadModel;
use App\OpenCode\Business\ReadModel\SostoyanieWorkspaceReadModel;
use App\OpenCode\Business\Utils\SlugWorkspace;

/**
 * Состояние workspace для интерфейса.
 *
 * `canAcceptMessages` повторяет правило гейта: статус RUNNING, сессия не в
 * архиве и последний ход не выполняется.
 */
final class PoluchitSostoyanieWorkspaceQuery
{
    public function __construct(
        private SessiyaOpenCodeRepository $sessionRepository,
        private HodOpenCodeRepository $turnRepository,
        private UmeetProveryatRekvizityOpenCode $credentials,
        private UmeetUpravlyatProektamiWorkspace $projectami,
    ) {
    }

    /**
     * @throws \DomainException если workspace не найден или принадлежит другому пользователю
     */
    public function handle(PoluchitSostoyanieWorkspace $query): SostoyanieWorkspaceReadModel
    {
        $accountId = $this->credentials->tekuschiiAccountId();
        $workspaceId = trim($query->workspaceId);

        if ($workspaceId === '') {
            throw new \DomainException('opencode.workspace.not_found');
        }

        $session = $this->sessionRepository->getById($workspaceId);
        $session->trebovatVladelca($accountId);

        $workspace = $session->getWorkspace();

        if (!$session->estWorkspace() || $workspace === null) {
            throw new \DomainException('opencode.workspace.not_found');
        }

        $lastTurn = $this->turnRepository->findPosledniiPoSessii($workspaceId);
        $canAcceptMessages = $workspace->getStatus() === StatusWorkspace::RUNNING
            && !$session->estArhiv()
            && !($lastTurn instanceof HodOpenCode && $lastTurn->estVProzesse());

        return new SostoyanieWorkspaceReadModel(
            $session->getId(),
            $session->getTitle(),
            $workspace->getBranch(),
            $workspace->getStatus()->value,
            $workspace->isReadOnly(),
            $session->estArhiv(),
            $session->getArchiveReason(),
            $workspace->getErrorKey(),
            array_map(
                static fn (ProektWorkspace $project): ProektWorkspaceReadModel => ProektWorkspaceReadModel::izModela($project),
                $workspace->getProjects(),
            ),
            $this->ideLink($session, $accountId),
            $canAcceptMessages,
            $lastTurn?->getId(),
            $lastTurn?->getStatus()->value,
        );
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
