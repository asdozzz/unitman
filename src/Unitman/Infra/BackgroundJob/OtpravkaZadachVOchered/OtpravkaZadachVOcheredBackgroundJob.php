<?php

declare(strict_types=1);

namespace App\Unitman\Infra\BackgroundJob\OtpravkaZadachVOchered;

use App\BackgroundJob\Infra\Service\AbstractBackgroundJob;
use App\Unitman\Business\Model\ZadachaDlyOcheredi;
use App\Unitman\Infra\Repository\ZadachaDlyOcherediRepository;
use Spiral\Goridge\RPC\RPC;
use Spiral\RoadRunner\Jobs\Jobs;

final class OtpravkaZadachVOcheredBackgroundJob extends AbstractBackgroundJob
{
    public function __construct(
        private ZadachaDlyOcherediRepository $zadachaDlyOcherediRepository,
        private string $roadRunnerRpcAddress,
    ) {
    }

    public function getName(): string
    {
        return 'otpravkaZadachVOchered';
    }

    public function run(): bool
    {
        $zadachi = $this->zadachaDlyOcherediRepository->poluchitZadachiNaObrabotku(200);

        $tree = array();

        foreach ($zadachi as $zadacha) {
            $tree[$zadacha->queueName][] = $zadacha;
        }

        $jobs = new Jobs(RPC::create($this->roadRunnerRpcAddress));

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
