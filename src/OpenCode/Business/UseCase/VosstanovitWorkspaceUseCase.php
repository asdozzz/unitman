<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UseCase;

use App\OpenCode\Business\Command\VosstanovitWorkspace;
use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatWorkspace;

/**
 * Восстановление workspace.
 *
 * Допустимые статусы — ARCHIVED и ERROR: архивный контейнер запускается той же
 * операцией, что и созданный, без повторного клонирования и первого промпта.
 */
final class VosstanovitWorkspaceUseCase
{
    private const RESTORE_FROM = [StatusWorkspace::ARCHIVED, StatusWorkspace::ERROR];

    public function __construct(
        private SessiyaOpenCodeRepository $sessionRepository,
        private UmeetProveryatRekvizityOpenCode $credentials,
        private UmeetUpravlyatWorkspace $workspaceManager,
    ) {
    }

    /**
     * @throws \DomainException если workspace не найден или его нельзя восстановить
     */
    public function handle(VosstanovitWorkspace $command): void
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

        if ($workspace->getStatus() === StatusWorkspace::STARTING) {
            return;
        }

        if (!in_array($workspace->getStatus(), self::RESTORE_FROM, true)) {
            throw new \DomainException('opencode.workspace.not_running');
        }

        if (!$this->sessionRepository->perevestiStatusWorkspace(
            $workspaceId,
            self::RESTORE_FROM,
            StatusWorkspace::STARTING,
        )) {
            $fresh = $this->sessionRepository->getById($workspaceId);

            if ($fresh->getWorkspace()?->getStatus() === StatusWorkspace::STARTING) {
                return;
            }

            throw new \DomainException('opencode.workspace.operation_failed');
        }

        $fresh = $this->sessionRepository->getById($workspaceId);
        $this->workspaceManager->vosstanovit($fresh);
        $this->sessionRepository->update($fresh);
    }
}
