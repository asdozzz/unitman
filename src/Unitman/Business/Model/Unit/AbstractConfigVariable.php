<?php

namespace App\Unitman\Business\Model\Unit;

abstract class AbstractConfigVariable implements ConfigVariable
{
    function toArray(): array
    {
        return array(
            'id' => $this->getId(),
            'label' => $this->getLabel(),
            'type' => $this->getType(),
            'defaultValue' => $this->getDefaultValue(),
            'options' => $this->getOptions()
        );
    }

    function getOptions(): array
    {
        return array();
    }
}
