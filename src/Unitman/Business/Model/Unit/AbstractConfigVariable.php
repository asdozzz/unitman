<?php

namespace App\Unitman\Business\Model\Unit;

use App\Unitman\Business\Model\Unit\ConfigUnita\PomoschnikSsylki;

abstract class AbstractConfigVariable implements ConfigVariable
{
    private ?PomoschnikSsylki $pomoschnikSsylki = null;

    function toArray(): array
    {
        return array(
            'id' => $this->getId(),
            'label' => $this->getLabel(),
            'type' => $this->getType(),
            'defaultValue' => $this->getDefaultValue(),
            'options' => $this->getOptions(),
            'helper' => $this->poluchitPomoschnikaSsylki()?->toArray()
        );
    }

    function getOptions(): array
    {
        return array();
    }

    public function poluchitPomoschnikaSsylki(): ?PomoschnikSsylki
    {
        return $this->pomoschnikSsylki;
    }

    public function ustanovitPomoschnikaSsylki(PomoschnikSsylki $pomoschnikSsylki): void
    {
        $this->pomoschnikSsylki = $pomoschnikSsylki;
    }
}
