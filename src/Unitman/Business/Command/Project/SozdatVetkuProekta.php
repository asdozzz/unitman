<?php

declare(strict_types=1);

namespace App\Unitman\Business\Command\Project;

final class SozdatVetkuProekta
{
    public function __construct(
        public readonly string $projectId,
        public readonly string $nazvanie,
        public readonly string $otVetki,
    )
    {
    }
}
