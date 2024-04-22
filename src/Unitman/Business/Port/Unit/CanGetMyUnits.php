<?php

namespace App\Unitman\Business\Port\Unit;
use App\Unitman\Business\Command\Unit\GetMyUnits;
use App\Unitman\Business\ReadModel\Unit\SpisokUnitovReadModel;

interface CanGetMyUnits
{
    /**
     * @return SpisokUnitovReadModel[]
     * */
    function getMyUnits(GetMyUnits $command, string $authorId): array;
}
