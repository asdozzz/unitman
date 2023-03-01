<?php

namespace App\Unitman\Business\Command;

final class CheckAccessToRepo
{
    public function __construct(
        public readonly string $repoId
    )
    {
    }

}
