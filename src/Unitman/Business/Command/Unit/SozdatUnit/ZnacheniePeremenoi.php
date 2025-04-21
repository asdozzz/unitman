<?php

namespace App\Unitman\Business\Command\Unit\SozdatUnit;

final class ZnacheniePeremenoi
{
    public function __construct(
        public readonly string $id,
        public readonly string $value,
        public readonly string $type,
    )
    {
    }

}
