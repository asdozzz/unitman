<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UseCase;

use App\OpenCode\Business\Command\IzmenitUchastnika;
use App\OpenCode\Business\Model\RolUchastnikaSessii;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UchastnikiSessiiRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Utils\ProverkaDostupaKSessii;
use App\OpenCode\Business\Workspace\Model\WorkspaceFeatureFlags;
use App\OpenCode\Business\Workspace\UseCase\IzmenitUchastnikaVWorkspaceSessiiUseCase;

/**
 * Смена роли и реквизитов по умолчанию участника.
 *
 * Роль владельца не меняется: передача владения — отдельная явная операция.
 */
final class IzmenitUchastnikaUseCase
{
    public function __construct(
        private readonly SessiyaOpenCodeRepository $sessionRepository,
        private readonly UchastnikiSessiiRepository $uchastniki,
        private readonly UmeetProveryatRekvizityOpenCode $credentials,
        private readonly ProverkaDostupaKSessii $dostup,
        private readonly ?WorkspaceFeatureFlags $flags = null,
        private readonly ?IzmenitUchastnikaVWorkspaceSessiiUseCase $workspaceUseCase = null,
    ) {
    }

    public function handle(IzmenitUchastnika $command): void
    {
        if ($this->flags?->pisatVNoviyAgregat() === true && $this->workspaceUseCase !== null) {
            $this->workspaceUseCase->handle($command);

            return;
        }

        $accountId = $this->credentials->tekuschiiAccountId();
        $session = $this->sessionRepository->getById(trim($command->workspaceId));

        $this->dostup->trebovat($session, $accountId, RolUchastnikaSessii::OWNER);

        $uchastnikId = trim($command->accountId);
        $uchastnik = $this->uchastniki->find($session->getId(), $uchastnikId);

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
