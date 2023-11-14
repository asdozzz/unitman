<?php

namespace App\Unitman\Business\Model\Unit\ConfigVariable;

final class ConfigVariableId implements \Stringable
{
    private string $id;

    public function __construct(string $id)
    {
        if (empty($id)) {
            throw new \DomainException('unit.config.variable.id_is_empty');
        }
        $this->id = $id;
    }

    public function __toString()
    {
        return $this->id;
    }
}
