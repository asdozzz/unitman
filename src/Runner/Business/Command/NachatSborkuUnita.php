<?php

namespace App\Runner\Business\Command;

final class NachatSborkuUnita
{
    public function __construct(
        public readonly string $ProjectId,
        public readonly string $Name,
        public readonly string $Branch,
    )
    {
    }

}
