<?php

namespace App\Unitman\Business\ReadModel\Project;

final class OcheredProektovReadModel
{
    const SBORKA_PROEKTA = 'SBORKA_PROEKTA';
    const UDALENIE_PROEKTA = 'UDALENIE_PROEKTA';
    const OCHISTKA_PROEKTA = 'OCHISTKA_PROEKTA';

    public function __construct(
        public readonly int $id,
        public readonly string $projectId,
        public readonly string $queueName,
    )
    {
    }
}
