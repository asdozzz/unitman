<?php

namespace App\Unitman\Infra\Jobs;

use App\Unitman\Business\Model\SobitieIzHranilisha;
use App\Unitman\Infra\Jobs\SobitiyaIzHranilisha\SobitiyaIzHranilishaActivity;
use FluffyDiscord\RoadRunnerBundle\Worker\JobsWorker\JobsHandlerInterface;
use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class SobitieIzHranilishaJobsHandler implements JobsHandlerInterface
{
    const QUEUE_NAME = 'sobitiya_iz_hranilisha';

    public function __construct(private SerializerInterface $serializer, private SobitiyaIzHranilishaActivity $activity)
    {
    }


    public function isSupported(ReceivedTaskInterface $task): bool
    {
        return $task->getPipeline() === self::QUEUE_NAME;
    }

    public function handle(ReceivedTaskInterface $task): void
    {
        try {
            $model = $this->serializer->deserialize($task->getPayload(), $task->getName(), 'json');
            /** @var SobitieIzHranilisha $model*/
            $this->activity->obrabotatZadachu($model);
        } catch (\Exception $e) {
            var_dump('AAAAAAAAAAAAAAAAAAAA:'.$e->getMessage());
        }

    }
}
