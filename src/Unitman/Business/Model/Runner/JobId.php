<?php

namespace App\Unitman\Business\Model\Runner;

final class JobId implements \Stringable
{
    private string $value;

    public function __construct(string $value)
    {
        if (empty($value)) {
            throw new \DomainException('unit.runner_service.job_id_empty');
        }
        $this->value = $value;
    }

    public function __toString()
    {
        return $this->value;
    }
}
