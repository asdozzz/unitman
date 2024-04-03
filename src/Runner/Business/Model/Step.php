<?php

namespace App\Runner\Business\Model;

final class Step
{
    public function __construct(public readonly string $Command, public readonly string $Response, public readonly bool $Success, public readonly int $Unixtime)
    {
    }
}
