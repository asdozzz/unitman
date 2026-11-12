<?php

declare(strict_types=1);

namespace App\Unitman\Infra\Adapter;

use App\Unitman\Business\Port\Queue\ZadachaVOchered;
use App\Unitman\Infra\Jobs\ZadachaDlyOcherediService;

final class ZadachaVOcheredAdapter implements ZadachaVOchered
{
    public function __construct(private ZadachaDlyOcherediService $zadachaDlyOcherediService)
    {
    }

    public function dobavitZadachuVOchered(
        string $queueName,
        object $task,
        int $attemptForRetryForQueue = 1,
        int $retryDelayForQueue = 1
    ): void {
        $this->zadachaDlyOcherediService->dobavitZadachuVOchered(
            $queueName,
            $task,
            $attemptForRetryForQueue,
            $retryDelayForQueue
        );
    }
}
