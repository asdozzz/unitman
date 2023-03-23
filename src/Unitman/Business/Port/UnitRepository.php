<?php

namespace App\Unitman\Business\Port;

use App\Unitman\Business\Model\Unit;

interface UnitRepository
{
    public function getById(string $id): Unit;

    public function save(Unit $unit): void;
}
