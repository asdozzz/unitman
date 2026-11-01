<?php

namespace App\Unitman\Business\Model\Unit\ConfigUnita;

final class PomoschnikSsylki
{
    public readonly string $projectId;
    public readonly string $service;
    public readonly int $port;
    public readonly ?string $suffix;

    public function __construct(?string $projectId, ?string $service, ?int $port, ?string $suffix = null)
    {
        if (empty($projectId)) {
            throw new \DomainException('unit.config.pomoschnik.projectId_is_empty');
        }

        if (empty($service)) {
            throw new \DomainException('unit.config.pomoschnik.service_is_empty');
        }

        if (!isset($port) || $port < 0 || $port > 65535) {
            throw new \DomainException('unit.config.pomoschnik.port_invalid');
        }

        $this->projectId = $projectId;
        $this->service = $service;
        $this->port = $port;
        $this->suffix = $suffix;
    }

    static function fromArray(array $data): self
    {
        return new self(
            $data['projectId'] ?? null,
            $data['service'] ?? null,
            $data['port'] ?? null,
            $data['suffix'] ?? null,
        );
    }

    function toArray(): array
    {
        return [
            'projectId' => $this->projectId,
            'service' => $this->service,
            'port' => $this->port,
            'suffix' => $this->suffix,
        ];
    }
}
