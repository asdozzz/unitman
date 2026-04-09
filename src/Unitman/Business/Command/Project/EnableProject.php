<?php

namespace App\Unitman\Business\Command\Project;

final class EnableProject
{
    public function __construct(
        public readonly string $id,
    )
    {
    }
}
