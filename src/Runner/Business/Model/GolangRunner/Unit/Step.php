<?php

namespace App\Runner\Business\Model\GolangRunner\Unit;

final class Step
{
    public function __construct(public readonly string $Command, public readonly string $Response, public readonly bool $Success, public readonly int $Unixtime)
    {
    }
}
