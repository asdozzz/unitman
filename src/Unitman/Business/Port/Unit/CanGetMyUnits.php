<?php

namespace App\Unitman\Business\Port\Unit;
use App\Unitman\Business\Command\Unit\GetMyUnits;

interface CanGetMyUnits
{
    function getMyUnits(GetMyUnits $command, string $authorId): array;
}
