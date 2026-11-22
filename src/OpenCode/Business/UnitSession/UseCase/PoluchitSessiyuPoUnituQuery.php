<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UnitSession\UseCase;

use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatUnitami;
use App\OpenCode\Business\UnitSession\Port\UnitSessionRepository;
use App\OpenCode\Business\UnitSession\ReadModel\UnitSessionReadModel;

final class PoluchitSessiyuPoUnituQuery
{
    public function __construct(
        private UnitSessionRepository $sessions,
        private UmeetProveryatRekvizityOpenCode $credentials,
        private UmeetUpravlyatUnitami $unitami,
    ) {
    }

    public function handle(string $unitId): ?UnitSessionReadModel
    {
        $accountId = $this->credentials->tekuschiiAccountId();
        $sostoyanie = $this->unitami->poluchitSostoyanieUnita($unitId);

        if (!$sostoyanie->exists || !$sostoyanie->yavlyaetsyaRedaktorom($accountId)) {
            return null;
        }

        $session = $this->sessions->findAktivnayaPoUnitu($unitId);

        return $session === null ? null : UnitSessionReadModel::izModela($session);
    }
}
