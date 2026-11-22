<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UnitSession\UseCase;

use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetVypolnyatHodOpenCode;
use App\OpenCode\Business\UnitSession\Port\UnitSessionRepository;
use App\OpenCode\Business\UnitSession\Port\UnitSessionTurnRepository;
use App\OpenCode\Business\UnitSession\Utils\ProverkaDostupaKSessiiUnita;

final class PoluchitProgressHodaSessiiUnitaQuery
{
    public function __construct(
        private UnitSessionRepository $sessions,
        private UnitSessionTurnRepository $turns,
        private UmeetProveryatRekvizityOpenCode $credentials,
        private UmeetVypolnyatHodOpenCode $runner,
        private ProverkaDostupaKSessiiUnita $dostup,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function handle(string $turnId): ?array
    {
        $accountId = $this->credentials->tekuschiiAccountId();
        $turn = $this->turns->getById($turnId);
        $session = $this->sessions->getById($turn->getSessionId());
        $this->dostup->proverit($session, $accountId);

        return $this->runner->poluchitProgressHoda($turn->getWorkflowId())?->toArray();
    }
}
