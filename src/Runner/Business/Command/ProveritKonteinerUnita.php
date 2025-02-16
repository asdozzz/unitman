<?php

namespace App\Runner\Business\Command;

final class ProveritKonteinerUnita
{
    public function __construct(
        public readonly string $ProjectId,
        public readonly string $ProjectName,
        public readonly string $Id,
        public readonly string $Name,
    )
    {
    }
}
