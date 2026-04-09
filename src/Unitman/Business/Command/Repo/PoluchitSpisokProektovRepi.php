<?php

namespace App\Unitman\Business\Command\Repo;

readonly class PoluchitSpisokProektovRepi
{
    public function __construct(public string $id, public ?string $query)
    {
    }

}
