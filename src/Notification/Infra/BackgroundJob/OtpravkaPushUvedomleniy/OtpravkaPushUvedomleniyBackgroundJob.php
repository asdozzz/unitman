<?php

declare(strict_types=1);

namespace App\Notification\Infra\BackgroundJob\OtpravkaPushUvedomleniy;

use App\BackgroundJob\Infra\Service\AbstractBackgroundJob;
use App\BackgroundJob\Infra\Service\BackgroundJobGroup;
use App\Notification\Business\Model\NotificationPushJob;
use App\Notification\Business\Port\DostavkiRepository;
use App\Notification\Business\UseCase\SozdatUvedomlenieUseCase;
use App\Notification\Infra\Jobs\NotificationPushJobHandler;
use Psr\Log\LoggerInterface;
use Spiral\Goridge\RPC\RPC;
use Spiral\RoadRunner\Jobs\Jobs;
use Spiral\RoadRunner\Jobs\Task\PreparedTaskInterface;

final class OtpravkaPushUvedomleniyBackgroundJob extends AbstractBackgroundJob
{
    private const BATCH_SIZE = 200;
    private const LEASE_SECONDS = 300;

    public function __construct(
        private readonly DostavkiRepository $dostavki,
        private readonly string $roadRunnerRpcAddress,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getName(): string
    {
        return 'otpravka_push_uvedomleniy';
    }

    public function getGroup(): BackgroundJobGroup
    {
        return BackgroundJobGroup::producers;
    }

    public function run(): bool
    {
        $ids = $this->dostavki->zahvatitNaOtpravku(
            SozdatUvedomlenieUseCase::PUSH_CHANNEL,
            self::BATCH_SIZE,
            self::LEASE_SECONDS,
            time(),
        );

        if ($ids === []) {
            return true;
        }

        $jobs = new Jobs(RPC::create($this->roadRunnerRpcAddress));
        $queue = $jobs->connect(NotificationPushJobHandler::QUEUE_NAME);
        $tasks = [];

        foreach ($ids as $id) {
            /** @var PreparedTaskInterface $task */
            $task = $queue
                ->create(NotificationPushJob::class, json_encode(['notificationId' => $id], JSON_THROW_ON_ERROR))
                ->withAddedHeader('attempts', '3')
                ->withAddedHeader('retry-delay', '5');
            $tasks[] = $task;
        }

        try {
            $queue->dispatchMany(...$tasks);
        } catch (\Throwable $error) {
            $this->logger->error('Не удалось поставить push-уведомления в очередь RoadRunner.', [
                'count' => count($tasks),
                'exception' => $error,
            ]);
        }

        return true;
    }
}
