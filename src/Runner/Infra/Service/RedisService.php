<?php

namespace App\Runner\Infra\Service;

use Spiral\Goridge\RPC\RPC;
use Spiral\RoadRunner\KeyValue\Factory;

final class RedisService
{
    private Factory $factory;

    public function __construct(string $roadRunnerRpcAddress)
    {
        // Адрес RPC-сокета RoadRunner берётся из RR_RPC (.env) и совпадает
        // с rpc.listen в .rr.yaml.
        $this->factory = new Factory(RPC::create($roadRunnerRpcAddress));
    }

    function set(string $key, string $value): void
    {
        $storage = $this->getStorage();
        $storage->set($key, $value, 24*60);
    }

    function get(string $key): ?string
    {
        $storage = $this->getStorage();
        return $storage->get($key);
    }

    /**
     * @return \Spiral\RoadRunner\KeyValue\StorageInterface
     */
    private function getStorage(): \Spiral\RoadRunner\KeyValue\StorageInterface
    {
        $storage = $this->factory->select('redis');
        return $storage;
    }
}
