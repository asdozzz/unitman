<?php

declare(strict_types=1);

namespace App\Notification\Business\UseCase;

use App\Notification\Business\Command\OtpisatsyaOtPush;
use App\Notification\Business\Port\PushPodpiskiRepository;
use App\Notification\Business\Port\UmeetOpredelyatPolzovatelya;

final class OtpisatsyaOtPushUseCase
{
    public function __construct(
        private PushPodpiskiRepository $podpiski,
        private UmeetOpredelyatPolzovatelya $polzovatel,
    ) {
    }

    public function handle(OtpisatsyaOtPush $command): void
    {
        $endpoint = trim($command->endpoint);

        if ($endpoint === '') {
            throw new \DomainException('notification.push.endpoint_invalid');
        }

        $this->podpiski->deleteByEndpoint(
            $this->polzovatel->tekushiyAccountId(),
            $endpoint,
        );
    }
}
