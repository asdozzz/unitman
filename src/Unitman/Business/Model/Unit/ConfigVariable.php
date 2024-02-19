<?php

namespace App\Unitman\Business\Model\Unit;

interface ConfigVariable
{
    function toArray(): array;
    function getId(): string;

    function getType(): string;

    function getLabel(): string;

    function getDefaultValue(): string;

    function getOptions(): array;

    function validateValue(string $value): ?string;
}
