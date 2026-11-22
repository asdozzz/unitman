<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UnitSession\UseCase;

use App\OpenCode\Business\Model\HodOpenCode;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetVypolnyatHodOpenCode;
use App\OpenCode\Business\ReadModel\HodOpenCodeReadModel;
use App\OpenCode\Business\UnitSession\Port\UnitSessionRepository;
use App\OpenCode\Business\UnitSession\Port\UnitSessionTurnRepository;
use App\OpenCode\Business\UnitSession\Utils\ProverkaDostupaKSessiiUnita;

final class PoluchitIstoriyuSessiiUnitaQuery
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
     * @return list<HodOpenCodeReadModel>
     */
    public function handle(string $sessionId): array
    {
        $accountId = $this->credentials->tekuschiiAccountId();
        $session = $this->sessions->getById($sessionId);
        $this->dostup->proverit($session, $accountId);

        return array_map(
            fn (HodOpenCode $turn): HodOpenCodeReadModel => HodOpenCodeReadModel::izModela(
                $turn,
                $this->runner->kanalProgressa($turn->getWorkflowId()),
            ),
            $this->turns->findBySessionId($sessionId),
        );
    }
}
