<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UseCase;

use App\OpenCode\Business\Command\UdalitUchastnika;
use App\OpenCode\Business\Model\RolUchastnikaSessii;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UchastnikiSessiiRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Utils\ProverkaDostupaKSessii;
use App\OpenCode\Business\Workspace\Model\WorkspaceFeatureFlags;
use App\OpenCode\Business\Workspace\UseCase\UdalitUchastnikaIzWorkspaceSessiiUseCase;

final class UdalitUchastnikaUseCase
{
    public function __construct(
        private readonly SessiyaOpenCodeRepository $sessionRepository,
        private readonly UchastnikiSessiiRepository $uchastniki,
        private readonly UmeetProveryatRekvizityOpenCode $credentials,
        private readonly ProverkaDostupaKSessii $dostup,
        private readonly ?WorkspaceFeatureFlags $flags = null,
        private readonly ?UdalitUchastnikaIzWorkspaceSessiiUseCase $workspaceUseCase = null,
    ) {
    }

    public function handle(UdalitUchastnika $command): void
    {
        if ($this->flags?->pisatVNoviyAgregat() === true && $this->workspaceUseCase !== null) {
            $this->workspaceUseCase->handle($command);

            return;
        }

        $accountId = $this->credentials->tekuschiiAccountId();
        $session = $this->sessionRepository->getById(trim($command->workspaceId));

        $this->dostup->trebovat($session, $accountId, RolUchastnikaSessii::OWNER);

        $uchastnik = $this->uchastniki->find($session->getId(), trim($command->accountId));

        if ($uchastnik === null) {
            throw new \DomainException('opencode.participant.not_found');
        }

        $this->uchastniki->udalit($uchastnik);
    }
}
