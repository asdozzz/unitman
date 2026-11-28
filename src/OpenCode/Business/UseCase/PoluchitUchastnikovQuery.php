<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UseCase;

use App\OpenCode\Business\Command\PoluchitUchastnikov;
use App\OpenCode\Business\Model\RolUchastnikaSessii;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Model\UchastnikSessii;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UchastnikiSessiiRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\ReadModel\UchastnikSessiiReadModel;
use App\OpenCode\Business\Utils\ProverkaDostupaKSessii;
use App\OpenCode\Business\Workspace\Model\WorkspaceFeatureFlags;
use App\OpenCode\Business\Workspace\UseCase\PoluchitUchastnikovWorkspaceSessiiQuery;

/**
 * Участники workspace: доступно любому участнику, включая viewer.
 */
final class PoluchitUchastnikovQuery
{
    public function __construct(
        private readonly SessiyaOpenCodeRepository $sessionRepository,
        private readonly UchastnikiSessiiRepository $uchastniki,
        private readonly UmeetProveryatRekvizityOpenCode $credentials,
        private readonly ProverkaDostupaKSessii $dostup,
        private readonly ?WorkspaceFeatureFlags $flags = null,
        private readonly ?PoluchitUchastnikovWorkspaceSessiiQuery $workspaceQuery = null,
    ) {
    }

    /**
     * @return list<UchastnikSessiiReadModel>
     */
    public function handle(PoluchitUchastnikov $command): array
    {
        if ($this->flags?->chitatIzNovogoAgregata() === true && $this->workspaceQuery !== null) {
            return $this->workspaceQuery->handle($command);
        }

        $accountId = $this->credentials->tekuschiiAccountId();
        $session = $this->sessionRepository->getById(trim($command->workspaceId));

        $this->dostup->trebovat($session, $accountId, RolUchastnikaSessii::VIEWER);

        $uchastniki = $this->uchastniki->findBySessionId($session->getId());

        // Старые workspace до backfill: владелец ещё не имеет строки участника,
        // но в списке он должен быть.
        if (!$this->estVladelec($uchastniki, $session)) {
            array_unshift($uchastniki, $this->nevyavniyVladelec($session));
        }

        return array_map(
            static fn (UchastnikSessii $uchastnik): UchastnikSessiiReadModel
                => UchastnikSessiiReadModel::izModela($uchastnik),
            $uchastniki,
        );
    }

    /**
     * @param list<UchastnikSessii> $uchastniki
     */
    private function estVladelec(array $uchastniki, SessiyaOpenCode $session): bool
    {
        foreach ($uchastniki as $uchastnik) {
            if ($uchastnik->estVladelec()) {
                return true;
            }
        }

        return false;
    }

    private function nevyavniyVladelec(SessiyaOpenCode $session): UchastnikSessii
    {
        return UchastnikSessii::restore(
            $session->getId(),
            $session->getAccountId(),
            RolUchastnikaSessii::OWNER,
            $session->getCredentialId(),
            $session->getModel(),
            null,
            $session->getCreatedAt(),
            $session->getUpdatedAt(),
        );
    }
}
