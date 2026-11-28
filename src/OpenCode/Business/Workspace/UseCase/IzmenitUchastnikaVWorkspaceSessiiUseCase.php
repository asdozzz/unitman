<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Workspace\UseCase;

use App\OpenCode\Business\Command\IzmenitUchastnika;
use App\OpenCode\Business\Model\RolUchastnikaSessii;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Workspace\Port\WorkspaceParticipantsRepository;
use App\OpenCode\Business\Workspace\Port\WorkspaceSessionRepository;
use App\OpenCode\Business\Workspace\Utils\ProverkaDostupaWorkspaceSessii;

/**
 * Смена роли и реквизитов участника на самостоятельном агрегате.
 *
 * Правило «owner неизменяем» остаётся в модели `UchastnikSessii`.
 */
final class IzmenitUchastnikaVWorkspaceSessiiUseCase
{
    public function __construct(
        private readonly WorkspaceSessionRepository $workspaces,
        private readonly WorkspaceParticipantsRepository $uchastniki,
        private readonly UmeetProveryatRekvizityOpenCode $credentials,
        private readonly ProverkaDostupaWorkspaceSessii $dostup,
    ) {
    }

    public function handle(IzmenitUchastnika $command): void
    {
        $accountId = $this->credentials->tekuschiiAccountId();
        $workspace = $this->workspaces->getById(trim($command->workspaceId));

        $this->dostup->trebovat($workspace, $accountId, RolUchastnikaSessii::OWNER);

        $uchastnikId = trim($command->accountId);
        $uchastnik = $this->uchastniki->find($workspace->getId(), $uchastnikId);

        if ($uchastnik === null) {
            throw new \DomainException('opencode.participant.not_found');
        }

        $now = time();

        $uchastnik->izmenitRol(RolUchastnikaSessii::izStroki($command->role), $now);

        if ($command->credentialId !== null) {
            $credentialId = trim($command->credentialId) === '' ? null : trim($command->credentialId);

            if ($credentialId !== null) {
                $this->credentials->trebovatVladelca($uchastnikId, $credentialId);
            }

            $model = $command->model !== null ? $command->model : $uchastnik->getDefaultModel();
            $uchastnik->izmenitRekvizity($credentialId, $model, $now);
        } elseif ($command->model !== null) {
            $uchastnik->izmenitRekvizity($uchastnik->getDefaultCredentialId(), $command->model, $now);
        }

        $this->uchastniki->save($uchastnik);
    }
}
