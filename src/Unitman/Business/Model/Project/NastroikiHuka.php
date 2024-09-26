<?php

namespace App\Unitman\Business\Model\Project;

readonly class NastroikiHuka
{
    public function __construct(public bool $avtosozdanie, public bool $avtoobnovlenie, public bool $avtoudalenie)
    {
    }
}
