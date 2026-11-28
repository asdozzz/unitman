<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Workspace\UseCase;

use App\OpenCode\Business\Command\DobavitUchastnika;
use App\OpenCode\Business\Model\RolUchastnikaSessii;
use App\OpenCode\Business\Model\UchastnikSessii;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatProektamiWorkspace;
use App\OpenCode\Business\UseCase\DobavitUchastnikaUseCase;
use App\OpenCode\Business\Workspace\Port\WorkspaceParticipantsRepository;
use App\OpenCode\Business\Workspace\Port\WorkspaceSessionRepository;
use App\OpenCode\Business\Workspace\Utils\ProverkaDostupaWorkspaceSessii;

/**
 * Приглашение участника на самостоятельном агрегате.
 *
 * Сохраняет легаси-правила: только владелец, запрет владельца и роли `owner`,
 * проверка доступа кандидата ко всем проектам, лимит участников.
 */
final class DobavitUchastnikaVWorkspaceSessiiUseCase
{
    public function __construct(
        private readonly WorkspaceSessionRepository $workspaces,
        private readonly WorkspaceParticipantsRepository $uchastniki,
        private readonly UmeetProveryatRekvizityOpenCode $credentials,
        private readonly UmeetUpravlyatProektamiWorkspace $projectami,
        private readonly ProverkaDostupaWorkspaceSessii $dostup,
    ) {
    }

    public function handle(DobavitUchastnika $command): void
    {
        $accountId = $this->credentials->tekuschiiAccountId();
        $workspace = $this->workspaces->getById(trim($command->workspaceId));

        $this->dostup->trebovat($workspace, $accountId, RolUchastnikaSessii::OWNER);

        $uchastnikId = trim($command->accountId);

        if ($uchastnikId === '') {
            throw new \DomainException('opencode.participant.account_required');
        }

        if ($workspace->prinadlezhit($uchastnikId)) {
            throw new \DomainException('opencode.participant.already_owner');
        }

        $rol = RolUchastnikaSessii::izStroki($command->role);

        if ($rol === RolUchastnikaSessii::OWNER) {
            throw new \DomainException('opencode.participant.owner_role_immutable');
        }

        if ($this->uchastniki->find($workspace->getId(), $uchastnikId) !== null) {
            throw new \DomainException('opencode.participant.already_exists');
        }

        if (count($this->uchastniki->findByWorkspaceId($workspace->getId()))
            >= DobavitUchastnikaUseCase::MAX_UCHASTNIKOV) {
            throw new \DomainException('opencode.participant.limit_exceeded');
        }

        if (!$this->projectami->estDostupKProektam($workspace->getProjectIds(), $uchastnikId)) {
            throw new \DomainException('opencode.participant.project_access_denied');
        }

        $credentialId = $this->normalize($command->credentialId);

        if ($credentialId !== null) {
            $this->credentials->trebovatVladelca($uchastnikId, $credentialId);
        }

        $this->uchastniki->save(UchastnikSessii::create(
            $workspace->getId(),
            $uchastnikId,
            $rol,
            $credentialId,
            $command->model,
            $accountId,
            time(),
        ));
    }

    private function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
