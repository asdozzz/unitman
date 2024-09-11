<?php

namespace App\Unitman\Business\Model\SobitieIzHranilisha;

use App\Unitman\Business\Model\SobitieIzHranilisha\TipSobitiya;

final class DannieSobitiya
{
    public function __construct(public readonly TipSobitiya $tipSobitiya, public readonly string $vetka, public readonly int $unixtime)
    {
    }

}
