<?php

namespace App\Unitman\Business\Command\Project;

final class UdalitPeremenuyuIzProekta
{
    public function __construct(
        public readonly string $projectId,
        public readonly string $code,
    ) { }
}
