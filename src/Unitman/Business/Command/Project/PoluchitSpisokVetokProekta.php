<?php

namespace App\Unitman\Business\Command\Project;

final class PoluchitSpisokVetokProekta
{
    public function __construct(public readonly string $id, public readonly ?string $query = null)
    {
    }

}
