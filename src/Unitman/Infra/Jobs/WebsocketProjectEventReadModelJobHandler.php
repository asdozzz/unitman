<?php

namespace App\Unitman\Infra\Jobs;

use App\Unitman\Business\ReadModel\Project\WebsocketProjectEventReadModel;
use App\Utils\Service\LockService;
use FluffyDiscord\RoadRunnerBundle\Worker\JobsWorker\JobsHandlerInterface;
use Fresh\CentrifugoBundle\Service\CentrifugoInterface;
use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;

final class WebsocketProjectEventReadModelJobHandler implements JobsHandlerInterface
{
    const QUEUE_NAME = 'websocket_project_event';

    const CENTRIFUGO_CHANNEL = 'spisok_proektov';

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
            /** @var WebsocketProjectEventReadModel $model*/
            $this->centrifugo->publish($arr, self::CENTRIFUGO_CHANNEL);
        } finally {
            $lock->release();
        }
    }
}
