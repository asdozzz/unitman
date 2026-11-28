<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Workspace\UseCase;

use App\OpenCode\Business\Command\PoluchitUchastnikov;
use App\OpenCode\Business\Model\RolUchastnikaSessii;
use App\OpenCode\Business\Model\UchastnikSessii;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\ReadModel\UchastnikSessiiReadModel;
use App\OpenCode\Business\Workspace\Model\WorkspaceSession;
use App\OpenCode\Business\Workspace\Port\WorkspaceParticipantsRepository;
use App\OpenCode\Business\Workspace\Port\WorkspaceSessionRepository;
use App\OpenCode\Business\Workspace\Utils\ProverkaDostupaWorkspaceSessii;

/**
 * Участники workspace на самостоятельном агрегате.
 *
 * Контракт совпадает с легаси-путём: список доступен любому участнику,
 * включая viewer; владелец всегда присутствует в ответе, даже если строки
 * `owner` в таблице ещё нет.
 */
final class PoluchitUchastnikovWorkspaceSessiiQuery
{
    public function __construct(
        private readonly WorkspaceSessionRepository $workspaces,
        private readonly WorkspaceParticipantsRepository $uchastniki,
        private readonly UmeetProveryatRekvizityOpenCode $credentials,
        private readonly ProverkaDostupaWorkspaceSessii $dostup,
    ) {
    }

    /**
     * @return list<UchastnikSessiiReadModel>
     */
    public function handle(PoluchitUchastnikov $command): array
    {
        $accountId = $this->credentials->tekuschiiAccountId();
        $workspace = $this->workspaces->getById(trim($command->workspaceId));

        $this->dostup->trebovat($workspace, $accountId, RolUchastnikaSessii::VIEWER);

        $uchastniki = $this->uchastniki->findByWorkspaceId($workspace->getId());

        if (!$this->estVladelec($uchastniki)) {
            array_unshift($uchastniki, $this->nevyavniyVladelec($workspace));
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
    private function estVladelec(array $uchastniki): bool
    {
        foreach ($uchastniki as $uchastnik) {
            if ($uchastnik->estVladelec()) {
                return true;
            }
        }

        return false;
    }

    private function nevyavniyVladelec(WorkspaceSession $workspace): UchastnikSessii
    {
        return UchastnikSessii::restore(
            $workspace->getId(),
            $workspace->getAccountId(),
            RolUchastnikaSessii::OWNER,
            $workspace->getCredentialId(),
            $workspace->getModel(),
            null,
            $workspace->getCreatedAt(),
            $workspace->getUpdatedAt(),
        );
    }
}
