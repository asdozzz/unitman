<?php

namespace App\Unitman\Infra\Jobs;

use App\Unitman\Business\Model\WebhookEventJob;
use App\Unitman\Business\UseCase\Unit\WebhookEvent\OtpravitEventNaWebhookUseCase;
use App\Utils\Service\LockService;
use FluffyDiscord\RoadRunnerBundle\Worker\JobsWorker\JobsHandlerInterface;
use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class WebhookEventJobHandler implements JobsHandlerInterface
{
    public function __construct(
        private LockService $lockService,
        private SerializerInterface $serializer,
        private OtpravitEventNaWebhookUseCase $useCase
    )
    {
    }

    const QUEUE_NAME = 'webhook_event';
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
            $model = $this->serializer->deserialize($task->getPayload(), WebhookEventJob::class, 'json');
            /** @var WebhookEventJob $model*/
            $this->useCase->handle($model->id);
        } finally {
            $lock->release();
        }
    }
}
