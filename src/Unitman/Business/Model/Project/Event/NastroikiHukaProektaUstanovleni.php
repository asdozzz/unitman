<?php

namespace App\Unitman\Business\Model\Project\Event;

readonly class NastroikiHukaProektaUstanovleni
{
    public function __construct(public string $id, public bool $avtosozdanie, public bool $avtoobnovlenie, public bool $avtoudalenie)
    {
    }

}
