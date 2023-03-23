<?php

namespace App\Unitman\Business\Command\Project;

final class SetErrorWhenBuildProject
{
    public function __construct(
        public readonly string $id,
        public readonly string $error,
    )
    {
    }
}
