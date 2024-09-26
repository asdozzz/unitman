<?php

namespace App\Unitman\Business\ReadModel\Unit;

final class OcheredDlyProzesaSozdaniyaUnitaSystemoi
{
    const SOZDAN = 'SOZDAN';
    const SOBRAN = 'SOBRAN';
    const PODGOTOVLEN = 'PODGOTOVLEN';

    const ERROR = 'ERROR';

    public function __construct(
        public readonly string $unitId,
        public readonly string $state,
    )
    {
    }

}
