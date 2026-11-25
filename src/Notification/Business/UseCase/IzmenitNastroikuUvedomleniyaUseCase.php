<?php

declare(strict_types=1);

namespace App\Notification\Business\UseCase;

use App\Notification\Business\Command\IzmenitNastroikuUvedomleniya;
use App\Notification\Business\Port\NastroikiUvedomleniyRepository;
use App\Notification\Business\Port\UmeetOpredelyatPolzovatelya;

final class IzmenitNastroikuUvedomleniyaUseCase
{
    public function __construct(
        private NastroikiUvedomleniyRepository $nastroiki,
        private UmeetOpredelyatPolzovatelya $polzovatel,
    ) {
    }

    public function handle(IzmenitNastroikuUvedomleniya $command): void
    {
        if ($command->type->sistemniy()) {
            throw new \DomainException('notification.preference.system_type');
        }

        $this->nastroiki->save(
            $this->polzovatel->tekushiyAccountId(),
            $command->type,
            $command->enabled,
            time(),
        );
    }
}
