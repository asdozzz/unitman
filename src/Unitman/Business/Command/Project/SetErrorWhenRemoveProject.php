<?php

namespace App\Unitman\Business\Command\Project;

final class SetErrorWhenRemoveProject
{
    public function __construct(
        public readonly string $id,
        public readonly array $steps,
    )
    {
    }
}
