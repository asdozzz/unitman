<?php

namespace App\Unitman\Infra\Jobs;

use App\Unitman\Business\Model\SobitieIzHranilisha;
use App\Unitman\Infra\Jobs\SobitiyaIzHranilisha\SobitiyaIzHranilishaActivity;
use App\Utils\Service\LockService;
use FluffyDiscord\RoadRunnerBundle\Worker\JobsWorker\JobsHandlerInterface;
use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class SobitieIzHranilishaJobsHandler implements JobsHandlerInterface
{
    const QUEUE_NAME = 'sobitiya_iz_hranilisha';

    public function __construct(
        private SerializerInterface $serializer,
        private SobitiyaIzHranilishaActivity $activity,
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
            $model = $this->serializer->deserialize($task->getPayload(), $task->getName(), 'json');
            /** @var SobitieIzHranilisha $model*/
            $this->activity->obrabotatZadachu($model);
        } finally {
            $lock->release();
        }

    }
}
