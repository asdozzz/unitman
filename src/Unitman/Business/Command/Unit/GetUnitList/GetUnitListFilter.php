<?php

namespace App\Unitman\Business\Command\Unit\GetUnitList;

final class GetUnitListFilter
{
    public function __construct(public readonly bool $onlyMine = false)
    {
    }

}
