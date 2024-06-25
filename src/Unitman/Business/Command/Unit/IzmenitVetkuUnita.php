<?php

namespace App\Unitman\Business\Command\Unit;

final class IzmenitVetkuUnita
{
    public function __construct(public readonly string $id, public readonly string $newBranch)
    {
    }

}
