<?php

namespace App\Unitman\Business\Command\Project;

final class RemoveProject
{
    public function __construct(
        public readonly string $id,
        public readonly string $info,
    )
    {
    }
}
