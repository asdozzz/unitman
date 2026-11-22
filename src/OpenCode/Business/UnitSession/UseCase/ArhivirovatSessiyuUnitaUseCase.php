<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UnitSession\UseCase;

use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\UnitSession\Model\UnitSession;
use App\OpenCode\Business\UnitSession\Port\UnitSessionRepository;
use App\OpenCode\Business\UnitSession\Utils\ProverkaDostupaKSessiiUnita;

final class ArhivirovatSessiyuUnitaUseCase
{
    public function __construct(
        private UnitSessionRepository $sessions,
        private UmeetProveryatRekvizityOpenCode $credentials,
        private ProverkaDostupaKSessiiUnita $dostup,
    ) {
    }

    public function handle(string $sessionId): void
    {
        $accountId = $this->credentials->tekuschiiAccountId();
        $session = $this->sessions->getById($sessionId);
        $this->dostup->proverit($session, $accountId);
        $session->arhivirovat(UnitSession::ARCHIVE_REASON_MANUAL, time());
        $this->sessions->update($session);
    }
}
