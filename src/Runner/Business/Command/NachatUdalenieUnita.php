<?php

namespace App\Runner\Business\Command;

final class NachatUdalenieUnita
{
    public function __construct(
        public readonly string $ProjectId,
        public readonly string $Name,
    )
    {
    }
}
