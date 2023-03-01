<?php

namespace App\Unitman\Business\Command;

final class DeleteRepo
{
    public function __construct(
        public readonly string $repoId
    )
    {
    }

}
