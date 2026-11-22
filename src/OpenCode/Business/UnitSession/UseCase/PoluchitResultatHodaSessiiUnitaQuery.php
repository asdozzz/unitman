<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UnitSession\UseCase;

use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetVypolnyatHodOpenCode;
use App\OpenCode\Business\ReadModel\HodOpenCodeReadModel;
use App\OpenCode\Business\UnitSession\Port\UnitSessionRepository;
use App\OpenCode\Business\UnitSession\Port\UnitSessionTurnRepository;
use App\OpenCode\Business\UnitSession\Utils\ProverkaDostupaKSessiiUnita;

final class PoluchitResultatHodaSessiiUnitaQuery
{
    public function __construct(
        private UnitSessionRepository $sessions,
        private UnitSessionTurnRepository $turns,
        private UmeetProveryatRekvizityOpenCode $credentials,
        private UmeetVypolnyatHodOpenCode $runner,
        private ProverkaDostupaKSessiiUnita $dostup,
    ) {
    }

    public function handle(string $turnId): HodOpenCodeReadModel
    {
        $accountId = $this->credentials->tekuschiiAccountId();
        $turn = $this->turns->getById($turnId);
        $session = $this->sessions->getById($turn->getSessionId());
        $this->dostup->proverit($session, $accountId);

        if ($turn->estVProzesse()) {
            $result = $this->runner->poluchitResultatHoda($turn->getWorkflowId());

            if ($result !== null) {
                $turn->primenitRezultat($result, time());
                $this->turns->update($turn);
            }
        }

        return HodOpenCodeReadModel::izModela(
            $turn,
            $this->runner->kanalProgressa($turn->getWorkflowId()),
        );
    }
}
