<?php

namespace App\Unitman\Infra\BackgroundJob\SobitiyaIzHranilisha;

use FluffyDiscord\RoadRunnerBundle\Worker\JobsWorker\JobsHandlerInterface;
use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;

final class TestJobHandler implements JobsHandlerInterface
{

    public function isSupportedQueue(string $queueName): bool
    {
        return $queueName === 'local';
    }

    public function handle(ReceivedTaskInterface $task): void
    {
        file_put_contents(__DIR__.'/test.txt', $task->getPayload(), FILE_APPEND);
    }
}
