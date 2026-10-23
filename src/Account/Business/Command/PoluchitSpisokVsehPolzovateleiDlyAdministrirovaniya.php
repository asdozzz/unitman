<?php

namespace App\Account\Business\Command;

readonly class PoluchitSpisokVsehPolzovateleiDlyAdministrirovaniya
{
    public function __construct(
        public ?string $query = null
    )
    {
    }

}
