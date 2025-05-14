<?php

namespace App\Utils\Service;

use RoadRunner\Lock\Lock;
use Spiral\Goridge\RPC\RPC;
use Spiral\RoadRunner\Symfony\Lock\RoadRunnerStore;
use Symfony\Component\Lock\LockFactory;

final class LockService
{
    function makeLockFactory(): LockFactory
    {
        $lock = new Lock(RPC::create('tcp://127.0.0.1:6001'));
        $factory = new LockFactory(
            new RoadRunnerStore($lock)
        );
        return $factory;
    }
}
