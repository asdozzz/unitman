<?php

namespace App\Runner\Business\Model;

final class ResultatObnovleniyaUnita
{
    public function __construct(public readonly int $Success,public readonly string $Message, public readonly string $Config = '')
    {
    }
}
