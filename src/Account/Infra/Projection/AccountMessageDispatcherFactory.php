<?php

namespace App\Account\Infra\Projection;

use EventSauce\EventSourcing\SynchronousMessageDispatcher;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

final class AccountMessageDispatcherFactory
{
    /**
     * @var iterable<SyncProjectionForAccount>
     * */
    private iterable $syncProjections;

    public function __construct(#[TaggedIterator('account.sync_projection')] iterable $syncProjections)
    {
        $this->syncProjections = $syncProjections;
    }

    public function getMessageDispatcher(): SynchronousMessageDispatcher
    {
        return new SynchronousMessageDispatcher(...$this->syncProjections);
    }
}
