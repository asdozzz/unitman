<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UnitSession\UseCase;

use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\UnitSession\Model\UnitSessionFilter;
use App\OpenCode\Business\UnitSession\Port\UnitSessionRepository;
use App\OpenCode\Business\UnitSession\ReadModel\UnitSessionReadModel;

final class PoluchitSpisokSessiyUnitaQuery
{
    public function __construct(
        private UnitSessionRepository $sessions,
        private UmeetProveryatRekvizityOpenCode $credentials,
    ) {
    }

    /**
     * @return list<UnitSessionReadModel>
     */
    public function handle(bool $onlyActive = false, ?string $search = null): array
    {
        $accountId = $this->credentials->tekuschiiAccountId();
        $filter = new UnitSessionFilter($onlyActive, $search);

        return array_map(
            static fn ($session): UnitSessionReadModel => UnitSessionReadModel::izModela($session),
            $this->sessions->findByAccountId($accountId, $filter),
        );
    }
}
