<?php

declare(strict_types=1);

namespace App\Unitman\Business\Port\Unit;

interface UmeetOpoveshatObIzmeneniiUnita
{
    public function opoveshatObObnovlenii(string $unitId): void;
}
