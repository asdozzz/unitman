<?php

namespace App\Unitman\Business\Command\Project;

final class IzmenitZnacheniePeremenoiProekta
{
    public function __construct(
        public readonly string $projectId,
        public readonly string $code,
        public readonly string $newValue,
    ) { }
}
