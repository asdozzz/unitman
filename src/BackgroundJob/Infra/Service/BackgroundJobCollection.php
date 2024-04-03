<?php

namespace App\BackgroundJob\Infra\Service;

use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

final class BackgroundJobCollection
{
    /**
     * @var iterable<BackgroundJobInterface>
     * */
    private iterable $collection;

    public function __construct(#[TaggedIterator('app.background_job')] iterable $collection, private BackgroundJobService $backgroundJobService)
    {
        $this->collection = $collection;
    }

    public function startAll(): void
    {
        $this->backgroundJobService->init();
        foreach ($this->collection as $job) {
            $this->backgroundJobService->start($job);
        }
    }

    public function stopAll(): void
    {
        $this->backgroundJobService->init();
        foreach ($this->collection as $job) {
            $this->backgroundJobService->stop($job);
        }
    }
}
