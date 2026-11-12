<?php

declare(strict_types=1);

namespace App\BackgroundJob\Infra\Service;

use App\BackgroundJob\Infra\Repository\JobRepository;
use Spiral\Goridge\RPC\RPC;
use Spiral\RoadRunner\Services\Exception\ServiceException;
use Spiral\RoadRunner\Services\Manager;

final class BackgroundJobService
{
    /**
     * Сколько секунд RoadRunner ждёт перед перезапуском упавшего сервиса.
     * Значение должно совпадать с restart_sec в .rr.yaml для статически
     * объявленного сервиса unit_runner_jobs, иначе поведение после падения
     * отличается в зависимости от способа запуска.
     */
    private const RESTART_SEC = 10;

    private Manager $manager;

    public function __construct(
        private JobRepository $jobRepository,
        string $roadRunnerRpcAddress,
    ) {
        // Адрес RPC-сокета берётся из RR_RPC (.env): он же используется
        // в .rr.yaml (rpc.listen) и в LockService/RedisService.
        $this->manager = new Manager(RPC::create($roadRunnerRpcAddress));
    }

    /**
     * RoadRunner возвращает список статусов процесса (по одному на процесс
     * сервиса), поэтому это list, а не ассоциативный массив.
     *
     * @return list<array<string, mixed>>
     */
    private function getStatus(BackgroundJobInterface $job): array
    {
        try {
            return $this->manager->statuses(name: $job->getName());
        } catch (ServiceException) {
            return [];
        }
    }

    public function start(BackgroundJobInterface $job): void
    {
        $status = $this->getStatus($job);

        if (!empty($status)) {
            $this->manager->restart($job->getName());
        } else {
            $result = $this->manager->create(
                name: $job->getName(),
                command: 'php bin/console app:service:start '.$job->getName(),
                remainAfterExit: true,
                restartSec: self::RESTART_SEC,
                processNum: $job->getProcessNum(),
            );

            if (!$result) {
                throw new ServiceException('Failed to create RoadRunner service '.$job->getName());
            }
        }

        $this->jobRepository->start($job->getName(), $job->getName());
    }

    public function stop(BackgroundJobInterface $job): void
    {
        $status = $this->getStatus($job);

        if (!empty($status)) {
            $result = $this->manager->terminate(name: $job->getName());

            if (!$result) {
                throw new ServiceException('Failed to terminate RoadRunner service '.$job->getName());
            }
        }

        $this->jobRepository->stop($job->getName());
    }
}
