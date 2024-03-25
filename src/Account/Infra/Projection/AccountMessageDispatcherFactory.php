<?php

namespace App\Account\Infra\Projection;

use EventSauce\EventSourcing\SynchronousMessageDispatcher;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

final class AccountMessageDispatcherFactory
{
    public function getMessageDispatcher(): SynchronousMessageDispatcher
    {
        return new SynchronousMessageDispatcher();
    }
}
