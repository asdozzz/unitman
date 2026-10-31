<?php

namespace App\Unitman\Business\Model\Unit;

use App\Unitman\Business\Model\Unit\ConfigUnita\PomoschnikSsylki;

interface ConfigVariable
{
    function toArray(): array;
    function getId(): string;

    function getType(): string;

    function getLabel(): string;

    function getDefaultValue(): string;

    function getOptions(): array;

    function poluchitPomoschnikaSsylki(): ?PomoschnikSsylki;

    function validateValue(string $value): ?string;
}
