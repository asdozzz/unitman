<?php

namespace App\Unitman\Infra\Jobs;

use App\Unitman\Business\Model\SobitieIzHranilisha;
use App\Unitman\Infra\Jobs\SobitiyaIzHranilisha\SobitiyaIzHranilishaActivity;
use App\Utils\Service\LockService;
use FluffyDiscord\RoadRunnerBundle\Worker\JobsWorker\JobsHandlerInterface;
use Psr\Log\LoggerInterface;
use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class SobitieIzHranilishaJobsHandler implements JobsHandlerInterface
{
    const QUEUE_NAME = 'sobitiya_iz_hranilisha';

    /**
     * Верхний предел размера сериализованного задания — 6 MiB.
     *
     * Само тело события ограничено 5 MiB
     * (`SobitiePayloadDecoder::MAX_EVENT_PAYLOAD_BYTES`), остальное — запас на
     * обёртку задания при сериализации. Проверка выполняется до `deserialize`,
     * чтобы слишком большой payload не разбирался в памяти воркера; задание не
     * уходит в retry, а отбрасывается с предупреждением в лог.
     */
    public const MAX_JOB_PAYLOAD_BYTES = 6 * 1024 * 1024;

    public function __construct(
        private SerializerInterface $serializer,
        private SobitiyaIzHranilishaActivity $activity,
        private LockService $lockService,
        private LoggerInterface $logger,
    )
    {
    }


    public function isSupported(ReceivedTaskInterface $task): bool
    {
        return $task->getPipeline() === self::QUEUE_NAME;
    }

    public function handle(ReceivedTaskInterface $task): void
    {
        $payload = $task->getPayload();

        /*
         * Размер проверяется до десериализации: разбор многомегабайтного JSON
         * в воркере RoadRunner дороже, чем просто отбросить задание.
         */
        if (strlen($payload) > self::MAX_JOB_PAYLOAD_BYTES) {
            $this->logger->warning('sobitie_iz_hranilisha.job_payload_too_large', [
                'taskId' => $task->getId(),
                'payloadBytes' => strlen($payload),
                'limitBytes' => self::MAX_JOB_PAYLOAD_BYTES,
            ]);

            return;
        }

        $lockFactory = $this->lockService->makeLockFactory();
        $lock = $lockFactory->createLock(self::QUEUE_NAME.'.'.$task->getId());

        if (!$lock->acquire()) {
            return;
        }

        try {
            $model = $this->serializer->deserialize($payload, $task->getName(), 'json');
            /** @var SobitieIzHranilisha $model*/
            $this->activity->obrabotatZadachu($model);
        } finally {
            $lock->release();
        }

    }
}
