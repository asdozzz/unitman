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
     * Значение должно совпадать с restart_sec сервисов планировщика, которые
     * создаются через RPC, иначе поведение после падения отличается.
     */
    private const RESTART_SEC = 10;

    private const SCHEDULER_SERVICE_PREFIX = 'background_jobs_';

    private Manager $manager;

    private bool $schedulerEnabled;

    public function __construct(
        private JobRepository $jobRepository,
        string $roadRunnerRpcAddress,
        string $backgroundJobScheduler,
    ) {
        // Адрес RPC-сокета берётся из RR_RPC (.env): он же используется
        // в .rr.yaml (rpc.listen) и в LockService/RedisService.
        $this->manager = new Manager(RPC::create($roadRunnerRpcAddress));
        $this->schedulerEnabled = '1' === $backgroundJobScheduler;
    }

    public function isSchedulerEnabled(): bool
    {
        return $this->schedulerEnabled;
    }

    /**
     * RoadRunner возвращает список статусов процесса (по одному на процесс
     * сервиса), поэтому это list, а не ассоциативный массив.
     *
     * @return list<array<string, mixed>>
     */
    private function getStatusByName(string $name): array
    {
        try {
            return $this->manager->statuses(name: $name);
        } catch (ServiceException) {
            return [];
        }
    }

    public function start(BackgroundJobInterface $job): void
    {
        if ($this->schedulerEnabled) {
            $this->startGroup($job->getGroup());

            return;
        }

        $name = $job->getName();
        $status = $this->getStatusByName($name);

        if (!empty($status)) {
            $this->manager->restart($name);
        } else {
            $result = $this->manager->create(
                name: $name,
                command: 'php bin/console app:service:start '.$name,
                remainAfterExit: true,
                restartSec: self::RESTART_SEC,
                processNum: $job->getProcessNum(),
            );

            if (!$result) {
                throw new ServiceException('Failed to create RoadRunner service '.$name);
            }
        }

        $this->jobRepository->start($name, $name);
    }

    /**
     * Создаёт (или перезапускает) сервис планировщика группы.
     * Одна группа — один процесс, поэтому processNum всегда 1.
     */
    public function startGroup(BackgroundJobGroup $group): void
    {
        $serviceName = self::schedulerServiceName($group);
        $status = $this->getStatusByName($serviceName);

        if (!empty($status)) {
            $this->manager->restart($serviceName);
        } else {
            $result = $this->manager->create(
                name: $serviceName,
                command: 'php bin/console app:services:scheduler '.$group->value,
                remainAfterExit: true,
                restartSec: self::RESTART_SEC,
                processNum: 1,
            );

            if (!$result) {
                throw new ServiceException('Failed to create RoadRunner service '.$serviceName);
            }
        }

        $this->jobRepository->start($serviceName, $serviceName);
    }

    /**
     * Останавливает и сервис планировщика группы, и старый per-job сервис.
     * Так переключение BACKGROUND_JOB_SCHEDULER не оставляет дублирующих
     * процессов ни в одном из режимов.
     */
    public function stop(BackgroundJobInterface $job): void
    {
        $this->stopGroup($job->getGroup());

        $name = $job->getName();
        $status = $this->getStatusByName($name);

        if (!empty($status)) {
            $result = $this->manager->terminate(name: $name);

            if (!$result) {
                throw new ServiceException('Failed to terminate RoadRunner service '.$name);
            }
        }

        $this->jobRepository->stop($name);
    }

    public function stopGroup(BackgroundJobGroup $group): void
    {
        $serviceName = self::schedulerServiceName($group);
        $status = $this->getStatusByName($serviceName);

        if (!empty($status)) {
            $result = $this->manager->terminate(name: $serviceName);

            if (!$result) {
                throw new ServiceException('Failed to terminate RoadRunner service '.$serviceName);
            }
        }

        $this->jobRepository->stop($serviceName);
    }

    private static function schedulerServiceName(BackgroundJobGroup $group): string
    {
        return self::SCHEDULER_SERVICE_PREFIX.$group->value;
    }
}
