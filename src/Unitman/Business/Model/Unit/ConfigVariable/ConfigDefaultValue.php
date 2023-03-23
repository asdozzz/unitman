<?php

namespace App\Unitman\Business\Model\Unit\ConfigVariable;

final class ConfigDefaultValue implements \Stringable
{
    public function __construct(
        public readonly string $value
    )
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
