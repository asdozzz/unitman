<?php

namespace App\Runner\Business\Model;

final class ResultatOstanovkiUnita
{
    public function __construct(public readonly int $Success,public readonly string $Message)
    {
    }
}
