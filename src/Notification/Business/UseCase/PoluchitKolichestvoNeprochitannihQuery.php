<?php

declare(strict_types=1);

namespace App\Notification\Business\UseCase;

use App\Notification\Business\Port\UmeetOpredelyatPolzovatelya;
use App\Notification\Business\Port\UvedomleniyaRepository;

final class PoluchitKolichestvoNeprochitannihQuery
{
    public function __construct(
        private UvedomleniyaRepository $uvedomleniya,
        private UmeetOpredelyatPolzovatelya $polzovatel,
    ) {
    }

    public function handle(): int
    {
        return $this->uvedomleniya->schetchikNeprochitannih($this->polzovatel->tekushiyAccountId());
    }
}
