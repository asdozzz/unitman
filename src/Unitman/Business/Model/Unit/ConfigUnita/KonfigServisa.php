<?php

namespace App\Unitman\Business\Model\Unit\ConfigUnita;

use App\Unitman\Business\Model\Unit\ConfigUnita\KonfigServisa\CacheServisa;
use App\Unitman\Business\Model\Unit\ConfigUnita\KonfigServisa\PortServisa;

final class KonfigServisa
{
    public readonly string $name;
    /**
     * @var PortServisa[]
     * */
    public readonly array $ports;

    public ?CacheServisa $cache = null;

    public function __construct(?string $name, ?array $ports = [], ?array $cache = null)
    {
        if (empty($name)) {
            throw new \DomainException('unit.konfigServisa.name_is_empty');
        }

        $this->name = $name;
        $this->ports = array_map(fn(array $port) => PortServisa::fromArray($port), $ports ?? []);

        if (!empty($cache)) {
            $this->cache = CacheServisa::fromArray($cache);
        } else {
            $this->cache = null;
        }
    }

    static function fromServiceData(string $name, array $serviceData): self
    {
        $ports = [];
        if (isset($serviceData['ports'])) {
            $ports = $serviceData['ports'];
        }

        $cache = [];

        if (isset($serviceData['cache'])) {
            $cache = $serviceData['cache'];
        }

        return new self($name, $ports, $cache);
    }

    function toArray(): array
    {
        return [
            'name' => $this->name,
            'ports' => array_map(fn(PortServisa $portService) => $portService->toArray(), $this->ports),
            'cache' => $this->cache ? $this->cache->toArray() : [],
        ];
    }
}
