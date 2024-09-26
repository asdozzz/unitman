<?php

namespace App\Unitman\Business\ReadModel\ProjectList;

readonly class NastroikiHukaProekta
{
    public function __construct(public bool $avtosozdanie, public bool $avtoobnovlenie, public bool $avtoudalenie)
    {
    }
}
