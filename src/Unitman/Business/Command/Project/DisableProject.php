<?php

namespace App\Unitman\Business\Command\Project;

final class DisableProject
{
    public function __construct(
        public readonly string $id
    )
    {
    }

}
