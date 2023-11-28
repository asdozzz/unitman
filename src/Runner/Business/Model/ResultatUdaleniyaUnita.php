<?php

namespace App\Runner\Business\Model;

final class ResultatUdaleniyaUnita
{
    public function __construct(public readonly int $Success,public readonly string $Message)
    {
    }
}
