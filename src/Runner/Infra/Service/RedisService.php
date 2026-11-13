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

    /**
     * Запись с явным TTL в секундах. Нужна для прогресса задач раннера:
     * снапшоты живут ограниченное время и не должны засорять KV.
     */
    function setWithTtl(string $key, string $value, int $ttlSeconds): void
    {
        $storage = $this->getStorage();
        $storage->set($key, $value, $ttlSeconds);
    }

    function get(string $key): ?string
    {
        $storage = $this->getStorage();
        return $storage->get($key);
    }

    function delete(string $key): void
    {
        $this->getStorage()->delete($key);
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
