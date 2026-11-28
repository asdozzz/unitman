<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UseCase;

use App\OpenCode\Business\Command\UdalitWorkspace;
use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatWorkspace;
use App\OpenCode\Business\Workspace\Model\WorkspaceFeatureFlags;
use App\OpenCode\Business\Workspace\UseCase\UdalitWorkspaceSessiiUseCase;

/**
 * Удаление workspace.
 *
 * Сначала атомарно ставится DELETING и запускается финальная операция Runner
 * (`docker compose down -v`). Записи БД удаляет фоновая задача только после
 * успешного завершения операции.
 */
final class UdalitWorkspaceUseCase
{
    private const DELETE_FROM = [
        StatusWorkspace::RUNNING,
        StatusWorkspace::ARCHIVED,
        StatusWorkspace::ERROR,
    ];

    public function __construct(
        private SessiyaOpenCodeRepository $sessionRepository,
        private UmeetProveryatRekvizityOpenCode $credentials,
        private UmeetUpravlyatWorkspace $workspaceManager,
        private ?WorkspaceFeatureFlags $flags = null,
        private ?UdalitWorkspaceSessiiUseCase $workspaceUseCase = null,
    ) {
    }

    /**
     * @throws \DomainException если workspace не найден или ещё не готов к удалению
     */
    public function handle(UdalitWorkspace $command): void
    {
        if ($this->flags?->pisatVNoviyAgregat() === true && $this->workspaceUseCase !== null) {
            $this->workspaceUseCase->handle($command);

            return;
        }

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

        if ($workspace->getStatus() === StatusWorkspace::DELETING) {
            return;
        }

        if (!in_array($workspace->getStatus(), self::DELETE_FROM, true)) {
            throw new \DomainException('opencode.workspace.not_running');
        }

        if (!$this->sessionRepository->perevestiStatusWorkspace(
            $workspaceId,
            self::DELETE_FROM,
            StatusWorkspace::DELETING,
        )) {
            $fresh = $this->sessionRepository->getById($workspaceId);

            if ($fresh->getWorkspace()?->getStatus() === StatusWorkspace::DELETING) {
                return;
            }

            throw new \DomainException('opencode.workspace.operation_failed');
        }

        $fresh = $this->sessionRepository->getById($workspaceId);

        try {
            $this->workspaceManager->udalit($fresh);
            $this->sessionRepository->update($fresh);
        } catch (\Throwable $error) {
            $this->sessionRepository->perevestiStatusWorkspace(
                $workspaceId,
                [StatusWorkspace::DELETING],
                StatusWorkspace::ERROR,
            );

            $failed = $this->sessionRepository->getById($workspaceId);
            $failed->getWorkspace()?->otmetitOshibku('opencode.workspace.operation_failed', time());
            $this->sessionRepository->update($failed);

            throw $error;
        }
    }
}
