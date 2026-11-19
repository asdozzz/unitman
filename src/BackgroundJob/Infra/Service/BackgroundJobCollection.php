<?php

namespace App\BackgroundJob\Infra\Service;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class BackgroundJobCollection
{
    /**
     * @var iterable<BackgroundJobInterface>
     * */
    private iterable $collection;

    public function __construct(#[AutowireIterator('app.background_job')] iterable $collection, private BackgroundJobService $backgroundJobService)
    {
        $this->collection = $collection;
    }

    public function getJobByName(string $name): BackgroundJobInterface
    {
        $result = null;

        foreach ($this->collection as $job) {
            if ($job->getName() === $name) {
                $result = $job;
                break;
            }
        }

        if (empty($result)) {
            throw new \Exception('job.not_found_by_name');
        }

        return $result;
    }

    public function startAll(): void
    {
        if ($this->backgroundJobService->isSchedulerEnabled()) {
            // Один сервис на группу: иначе startAll() перезапускал бы
            // планировщик по разу на каждую задачу этой группы.
            foreach (BackgroundJobGroup::cases() as $group) {
                $this->backgroundJobService->startGroup($group);
            }

            return;
        }

        foreach ($this->collection as $job) {
            $this->backgroundJobService->start($job);
        }
    }

    public function stopAll(): void
    {
        // stop() останавливает и планировщик группы, и старый per-job сервис,
        // поэтому остановка корректна в обоих режимах.
        foreach ($this->collection as $job) {
            $this->backgroundJobService->stop($job);
        }
    }
}
