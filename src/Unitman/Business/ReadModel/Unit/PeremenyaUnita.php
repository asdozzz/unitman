<?php

namespace App\Unitman\Business\ReadModel\Unit;

use App\Unitman\Business\Model\Unit\ConfigVariable;

final class PeremenyaUnita
{
    public function __construct(private ConfigVariable $konfig, private string $value)
    {
    }

    public function toArray(): array
    {
        return [
            'konfig' => $this->konfig->toArray(),
            'value' => $this->value
        ];
    }
}
