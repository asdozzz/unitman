<?php

namespace App\Runner\Business\Command;

final class UstanovitResultatProverkiRabotosposobnosti
{
    public function __construct(
        public readonly string $id,
        public readonly bool $active,
        public readonly ?string $dockerStats = null,
        public readonly ?string $memInfo = null
    )
    {
    }

}
