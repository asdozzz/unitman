<?php

namespace App\Unitman\Business\ReadModel\ProjectList;

final class ProjectListVariable
{
    public function __construct(
        public readonly string $tip,
        public readonly string $code,
        public readonly string $value,
    )
    {
    }
}
