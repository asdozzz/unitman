<?php

namespace App\Unitman\Infra\Jobs;

use App\Unitman\Business\ReadModel\Unit\WebsocketUnitEventReadModel;
use App\Utils\Service\LockService;
use FluffyDiscord\RoadRunnerBundle\Worker\JobsWorker\JobsHandlerInterface;
use Fresh\CentrifugoBundle\Service\CentrifugoInterface;
use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class WebsocketUnitEventReadModelJobHandler implements JobsHandlerInterface
{
    const QUEUE_NAME = 'websocket_unit_event';

    public function __construct(
        private CentrifugoInterface $centrifugo,
        private LockService $lockService
    )
    {
    }


    public function isSupported(ReceivedTaskInterface $task): bool
    {
        return $task->getPipeline() === self::QUEUE_NAME;
    }

    public function handle(ReceivedTaskInterface $task): void
    {
        $lockFactory = $this->lockService->makeLockFactory();
        $lock = $lockFactory->createLock(self::QUEUE_NAME.'.'.$task->getId());

        if (!$lock->acquire()) {
            return;
        }

        try {
            $arr = json_decode($task->getPayload(), true);
            /** @var WebsocketUnitEventReadModel $model*/
            $this->centrifugo->publish($arr, 'spisok_unitov');
        } finally {
            $lock->release();
        }

    }
}
