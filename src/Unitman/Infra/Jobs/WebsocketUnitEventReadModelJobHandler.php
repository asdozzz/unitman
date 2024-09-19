<?php

namespace App\Unitman\Infra\Jobs;

use App\Unitman\Business\ReadModel\Unit\WebsocketUnitEventReadModel;
use FluffyDiscord\RoadRunnerBundle\Worker\JobsWorker\JobsHandlerInterface;
use Fresh\CentrifugoBundle\Service\CentrifugoInterface;
use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class WebsocketUnitEventReadModelJobHandler implements JobsHandlerInterface
{
    const QUEUE_NAME = 'websocket_unit_event';

    public function __construct(private SerializerInterface $serializer, private CentrifugoInterface $centrifugo)
    {
    }


    public function isSupported(ReceivedTaskInterface $task): bool
    {
        return $task->getPipeline() === self::QUEUE_NAME;
    }

    public function handle(ReceivedTaskInterface $task): void
    {
        $arr = json_decode($task->getPayload(), 1);
        /** @var WebsocketUnitEventReadModel $model*/
        $this->centrifugo->publish($arr, 'spisok_unitov');
    }
}
