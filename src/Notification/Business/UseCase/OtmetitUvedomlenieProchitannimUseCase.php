<?php

declare(strict_types=1);

namespace App\Notification\Business\UseCase;

use App\Notification\Business\Port\UmeetOpredelyatPolzovatelya;
use App\Notification\Business\Port\UvedomleniyaRepository;

final class OtmetitUvedomlenieProchitannimUseCase
{
    public function __construct(
        private UvedomleniyaRepository $uvedomleniya,
        private UmeetOpredelyatPolzovatelya $polzovatel,
    ) {
    }

    public function handle(string $notificationId): bool
    {
        $accountId = $this->polzovatel->tekushiyAccountId();

        if (!$this->uvedomleniya->estPoluchatelem($notificationId, $accountId)) {
            throw new \DomainException('notification.not_found');
        }

        return $this->uvedomleniya->otmetitProchitannim($notificationId, $accountId, time());
    }
}
