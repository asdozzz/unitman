<?php

namespace App\Unitman\Business\Model\Unit\ConfigVariable\UnitConfigVariable;

final class UnitOptions
{
    private string $projectCode;

    public function __construct(string $projectCode)
    {
        if (empty($projectCode)) {
            throw new \DomainException('unit.config.variable.unit_options.projectCode_is_empty');
        }

        $this->projectCode = $projectCode;
    }

    static function fromArray(array $options): static
    {
        return new static($options['projectCode'] ?? '');
    }
    public function toArray(): array
    {
        return ['projectCode' => $this->projectCode];
    }

}
