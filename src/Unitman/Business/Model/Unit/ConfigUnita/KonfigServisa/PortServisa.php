<?php

namespace App\Unitman\Business\Model\Unit\ConfigUnita\KonfigServisa;

final class PortServisa
{
    /**/
    public readonly int $port;
    public readonly string $type;

    public function __construct(?string $type, ?int $port)
    {
        if (empty($type)) {
            throw new \Exception('unit.config.port.type.is_empty');
        }

        if (!isset($port) || $port < 0 || $port > 65535) {
            throw new \Exception('unit.config.port.invalid');
        }
        $this->port = $port;
        $this->type = $type;
    }

    static function fromArray(array $portData): self
    {
        return new self($portData['type'] ?? null, $portData['port'] ?? null);
    }

    function toArray(): array
    {
        return [
            'type' => $this->type,
            'port' => $this->port,
        ];
    }
}
