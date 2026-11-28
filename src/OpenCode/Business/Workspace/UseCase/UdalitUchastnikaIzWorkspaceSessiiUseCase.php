<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Workspace\UseCase;

use App\OpenCode\Business\Command\UdalitUchastnika;
use App\OpenCode\Business\Model\RolUchastnikaSessii;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Workspace\Port\WorkspaceParticipantsRepository;
use App\OpenCode\Business\Workspace\Port\WorkspaceSessionRepository;
use App\OpenCode\Business\Workspace\Utils\ProverkaDostupaWorkspaceSessii;

/**
 * Удаление участника на самостоятельном агрегате.
 *
 * Удалять может только владелец; владельца удалить нельзя.
 */
final class UdalitUchastnikaIzWorkspaceSessiiUseCase
{
    public function __construct(
        private readonly WorkspaceSessionRepository $workspaces,
        private readonly WorkspaceParticipantsRepository $uchastniki,
        private readonly UmeetProveryatRekvizityOpenCode $credentials,
        private readonly ProverkaDostupaWorkspaceSessii $dostup,
    ) {
    }

    public function handle(UdalitUchastnika $command): void
    {
        $accountId = $this->credentials->tekuschiiAccountId();
        $workspace = $this->workspaces->getById(trim($command->workspaceId));

        $this->dostup->trebovat($workspace, $accountId, RolUchastnikaSessii::OWNER);

        $uchastnik = $this->uchastniki->find($workspace->getId(), trim($command->accountId));

        if ($uchastnik === null) {
            throw new \DomainException('opencode.participant.not_found');
        }

        $this->uchastniki->udalit($uchastnik);
    }
}
