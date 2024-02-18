<?php

namespace App\Runner\Business\Command;

final class NachatSbrosPodgotovkiUnita
{
    public function __construct(
        public readonly string $UnitId,
        public readonly string $ProjectId,
        public readonly string $Name,
        public readonly array $Commands,
        public readonly array $Variables,
    )
    {
    }
}
