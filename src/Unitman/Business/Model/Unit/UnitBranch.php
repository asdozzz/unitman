<?php

namespace App\Unitman\Business\Model\Unit;

final class UnitBranch
{
    private string $name;

    public function __construct(string $name)
    {
        if (empty($name)) {
            throw new \DomainException('unit.branchName_is_empty');
        }

        $this->name = $name;
    }


    public function __toString(): string
    {
        return $this->name;
    }
}
