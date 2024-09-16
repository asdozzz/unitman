<?php

namespace App\Unitman\Business\Model;

final class ZadachaDlyOcheredi
{
    /**
     * @param non-empty-string $id
     * @param non-empty-string $queueName
     * @param non-empty-string $taskName
     * */
    public function __construct(
        public readonly string $id,
        public readonly string $queueName,
        public readonly string $taskName,
        public readonly string $payload,
        public readonly int $attemptForRetryForQueue = 1,
        public readonly int $retryDelayForQueue = 1,
        public readonly int $attempt = 0,
        public readonly ?string $error = null
    )
    {
    }

}
