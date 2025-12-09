<?php

namespace App\Unitman\Infra\BackgroundJob\OtpravkaZadachVOchered;

use App\BackgroundJob\Infra\Service\AbstractBackgroundJob;
use App\Unitman\Business\Model\ZadachaDlyOcheredi;
use App\Unitman\Infra\Repository\ZadachaDlyOcherediRepository;
use Spiral\Goridge\RPC\RPC;
use Spiral\RoadRunner\Jobs\Jobs;

final class OtpravkaZadachVOcheredBackgroundJob extends AbstractBackgroundJob
{
    public function __construct(private ZadachaDlyOcherediRepository $zadachaDlyOcherediRepository)
    {
    }

    function getName(): string
    {
        return 'otpravkaZadachVOchered';
    }

    function run(): bool
    {
        $zadachi = $this->zadachaDlyOcherediRepository->poluchitZadachiNaObrabotku(200);

        $tree = array();

        foreach ($zadachi as $zadacha) {
            $tree[$zadacha->queueName][] = $zadacha;
        }

        $jobs = new Jobs(RPC::create('tcp://127.0.0.1:6001'));

        foreach ($tree as $queueName => $tasks) {
            $queue = $jobs->connect($queueName);
            $data = [];
            foreach ($tasks as $task) {
                /** @var ZadachaDlyOcheredi $task*/
                $preparedTask = $queue->create($task->taskName, $task->payload)
                    ->withAddedHeader('attempts', (string) $task->attemptForRetryForQueue)
                    ->withAddedHeader('retry-delay', (string) $task->retryDelayForQueue);
                $data[] = $preparedTask;
            }
            try {
                $queue->dispatchMany(...$data);
                $this->zadachaDlyOcherediRepository->removeByIds(array_map(fn(ZadachaDlyOcheredi $item) => $item->id, $tasks));
                //$this->zadachaDlyOcherediRepository->setSuccess(array_map(fn(ZadachaDlyOcheredi $item) => $item->id, $tasks), 'success');
            } catch (\Exception $e) {
                $this->zadachaDlyOcherediRepository->setError(array_map(fn(ZadachaDlyOcheredi $item) => $item->id, $tasks), $e->getMessage());
            }

        }

        return true;
    }
}
