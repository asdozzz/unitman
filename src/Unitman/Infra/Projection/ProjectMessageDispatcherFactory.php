<?php

namespace App\Unitman\Infra\Projection;

use EventSauce\EventSourcing\SynchronousMessageDispatcher;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

final class ProjectMessageDispatcherFactory
{
    /**
     * @var iterable<int, SyncProjectionForProject>
     * */
    private iterable $syncProjections;

    public function __construct(#[TaggedIterator('project.sync_projection')] iterable $syncProjections)
    {
        $this->syncProjections = $syncProjections;
    }

    public function getMessageDispatcher(): SynchronousMessageDispatcher
    {
        return new SynchronousMessageDispatcher(...$this->syncProjections);
    }
}
