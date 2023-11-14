<?php

namespace App\Unitman\Business\Model\Unit\ConfigVariable;

final class ConfigVariableLabel implements \Stringable
{
    private string $label;

    public function __construct(string $label)
    {
        if (empty($label)) {
            throw new \DomainException('unit.config.variable.label_is_empty');
        }

        if (strlen($label) < 3 || strlen($label) > 100) {
            throw new \DomainException('unit.config.variable.length_label_invalid');
        }

        if (!preg_match('/[a-zA-Z_а-яА-ЯЁё ]+/mu', $label)) {
            throw new \DomainException('unit.config.variable.label_invalid');
        }

        $this->label = $label;
    }

    public function __toString()
    {
        return $this->label;
    }
}
