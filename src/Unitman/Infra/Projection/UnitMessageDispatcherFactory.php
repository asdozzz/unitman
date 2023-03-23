<?php

namespace App\Unitman\Infra\Projection;

use EventSauce\EventSourcing\SynchronousMessageDispatcher;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

final class UnitMessageDispatcherFactory
{
    /**
     * @var iterable<SyncProjectionForUnit>
     * */
    private iterable $syncProjections;

    public function __construct(#[TaggedIterator('project.sync_unit')] iterable $syncProjections)
    {
        $this->syncProjections = $syncProjections;
    }

    public function getMessageDispatcher(): SynchronousMessageDispatcher
    {
        return new SynchronousMessageDispatcher(...$this->syncProjections);
    }
}
