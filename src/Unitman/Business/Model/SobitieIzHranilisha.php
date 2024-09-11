<?php

namespace App\Unitman\Business\Model;

final class SobitieIzHranilisha
{
    public function __construct(
        public readonly string $id,
        public readonly string $projectId,
        public readonly string $eventPayload
    )
    {
    }

}
