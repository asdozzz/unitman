<?php

namespace App\Unitman\Business\Model\Unit;

interface ConfigVariable
{
    function toArray(): array;
    function getId(): string;

    function validateValue(string $value): ?string;
}
