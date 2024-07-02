<?php

namespace App\BackgroundJob\Infra\Service;

use App\BackgroundJob\Infra\Repository\JobRepository;
use Spiral\Goridge\RPC\RPC;
use Spiral\RoadRunner\Services\Exception\ServiceException;
use Spiral\RoadRunner\Services\Manager;

final class BackgroundJobService
{
    private Manager $manager;
    public function __construct(private JobRepository $jobRepository)
    {
        $this->manager = new Manager(RPC::create('tcp://127.0.0.1:6001'));
    }

    function init(): void
    {
        $this->jobRepository->init();
    }

    private function getStatus(BackgroundJobInterface $job): array
    {
        try {
            $status = $this->manager->statuses(name: $job->getName());

            return $status;
        } catch (ServiceException) {
            return [];
        }

    }

    function start(BackgroundJobInterface $job): void
    {
        $status = $this->getStatus($job);

        if (!empty($status)) {
            $this->manager->restart($job->getName());
        } else {
            $result = $this->manager->create(
                name: $job->getName(),
                command: 'php bin/console app:service:start '.$job->getName(),
                remainAfterExit: true,
                restartSec: 1
            );

            if (!$result) {
                throw new ServiceException('Service creation failed.');
            }
        }

        $this->jobRepository->start($job->getName(), $job->getName());

    }

    function stop(BackgroundJobInterface $job): void
    {
        $status = $this->getStatus($job);

        if (!empty($status)) {
            $result = $this->manager->terminate(name: $job->getName());

            if (!$result) {
                throw new ServiceException('Service termination failed.');
            }
        }

        $this->jobRepository->stop($job->getName());

    }
}
