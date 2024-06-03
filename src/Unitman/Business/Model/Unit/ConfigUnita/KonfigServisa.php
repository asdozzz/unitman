<?php

namespace App\Unitman\Business\Model\Unit\ConfigUnita;

use App\Unitman\Business\Model\Unit\ConfigUnita\KonfigServisa\PortServisa;

final class KonfigServisa
{
    public readonly string $name;
    /**
     * @var PortServisa[]
     * */
    public readonly array $ports;

    public function __construct(?string $name, ?array $ports)
    {
        if (empty($name)) {
            throw new \DomainException('unit.konfigServisa.name_is_empty');
        }

        if (empty($ports)) {
            throw new \DomainException('unit.konfigServisa.ports_is_empty');
        }
        $this->name = $name;
        $this->ports = array_map(fn(array $port) => PortServisa::fromArray($port), $ports);
    }

    static function fromServiceData(string $name, array $serviceData): self
    {
        $ports = [];
        if (!empty($serviceData['ports'])) {
            $ports = $serviceData['ports'];
        }

        return new self($name, $ports);
    }

    function toArray(): array
    {
        return [
            'name' => $this->name,
            'ports' => array_map(fn(PortServisa $portService) => $portService->toArray(), $this->ports),
        ];
    }
}
