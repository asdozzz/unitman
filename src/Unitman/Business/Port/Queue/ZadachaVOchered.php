<?php

declare(strict_types=1);

namespace App\Unitman\Business\Port\Queue;

interface ZadachaVOchered
{
    /**
     * @param non-empty-string $queueName
     */
    public function dobavitZadachuVOchered(
        string $queueName,
        object $task,
        int $attemptForRetryForQueue = 1,
        int $retryDelayForQueue = 1
    ): void;
}
