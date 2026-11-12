<?php

declare(strict_types=1);

namespace App\Utils\Service;

use RoadRunner\Lock\Lock;
use Spiral\Goridge\RPC\RPC;
use Spiral\RoadRunner\Symfony\Lock\RoadRunnerStore;
use Symfony\Component\Lock\LockFactory;

final class LockService
{
    public function __construct(private string $roadRunnerRpcAddress)
    {
    }

    public function makeLockFactory(): LockFactory
    {
        // Адрес RPC-сокета берётся из RR_RPC (.env) и совпадает с rpc.listen в .rr.yaml.
        $lock = new Lock(RPC::create($this->roadRunnerRpcAddress));

        return new LockFactory(new RoadRunnerStore($lock));
    }
}
