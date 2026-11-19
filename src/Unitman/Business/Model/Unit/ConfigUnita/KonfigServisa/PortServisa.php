<?php

namespace App\Unitman\Business\Model\Unit\ConfigUnita\KonfigServisa;

use App\Unitman\Business\Utils\IdePort;

final class PortServisa
{
    /**/
    public readonly int $port;
    public readonly string $type;
    public readonly string $startUri;

    public function __construct(?string $type, ?int $port, ?string $startUri)
    {
        $type = strtolower(trim((string)$type));
        if ($type === '') {
            throw new \Exception('unit.config.port.type.is_empty');
        }

        if (!isset($port) || $port < 0 || $port > 65535) {
            throw new \Exception('unit.config.port.invalid');
        }

        // Порт редактора зарезервирован: IDE публикуется в network namespace
        // юнита, поэтому сервис на этом порту перекрыл бы редактор.
        if (IdePort::yavlyaetsyaZarezervirovannim($port)) {
            throw new \Exception('unit.config.port.invalid');
        }

        $this->port = $port;
        $this->type = $type;
        $this->startUri = $startUri ?? "";
    }

    static function fromArray(array $portData): self
    {
        return new self($portData['type'] ?? null, $portData['port'] ?? null, $portData['startUri'] ?? null);
    }

    function toArray(): array
    {
        return [
            'type' => $this->type,
            'port' => $this->port,
            'startUri' => $this->startUri,
        ];
    }
}
