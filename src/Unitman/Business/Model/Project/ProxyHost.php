<?php

namespace App\Unitman\Business\Model\Project;

final class ProxyHost
{
    private string $host;

    public function __construct(string $host)
    {
        if (empty($host)) {
            throw new \DomainException('project.host_is_empty');
        }

        if (!filter_var($host, FILTER_VALIDATE_URL)) {
            throw new \DomainException('project.host_invalid');
        }

        $this->host = $host;
    }
    public function __toString(): string
    {
        return $this->host;
    }
}
