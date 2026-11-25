<?php

declare(strict_types=1);

namespace App\Notification\Business\UseCase;

use App\Notification\Business\Port\UmeetOpredelyatPolzovatelya;
use App\Notification\Business\Port\UvedomleniyaRepository;
use App\Notification\Business\ReadModel\SpisokUvedomleniyReadModel;

final class PoluchitSpisokUvedomleniyQuery
{
    private const DEFAULT_LIMIT = 50;
    private const MAX_LIMIT = 100;

    public function __construct(
        private UvedomleniyaRepository $uvedomleniya,
        private UmeetOpredelyatPolzovatelya $polzovatel,
    ) {
    }

    public function handle(int $limit = self::DEFAULT_LIMIT, bool $onlyActionable = false): SpisokUvedomleniyReadModel
    {
        $accountId = $this->polzovatel->tekushiyAccountId();
        $limit = max(1, min(self::MAX_LIMIT, $limit));

        return new SpisokUvedomleniyReadModel(
            $this->uvedomleniya->findDlyaPolzovatelya($accountId, $limit, $onlyActionable),
            $this->uvedomleniya->schetchikNeprochitannih($accountId),
        );
    }
}
