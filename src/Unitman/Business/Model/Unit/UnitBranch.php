<?php

namespace App\Unitman\Business\Model\Unit;

final class UnitBranch
{
    private string $name;

    public function __construct(string $name)
    {
        $this->validate($name);

        $this->name = $name;
    }


    public function __toString(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return void
     */
    public function validate(string $name): void
    {
        if (empty($name)) {
            throw new \DomainException('unit.branch_is_empty');
        }
    }

    public function validateNewBranch(string $newName): void
    {
        $this->validate($newName);

        if ($this->name === $newName) {
            throw new \DomainException('unit.new_branch_equal_old_branch');
        }
    }
}
