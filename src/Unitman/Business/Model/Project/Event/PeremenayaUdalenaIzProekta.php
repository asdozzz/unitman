<?php

namespace App\Unitman\Business\Model\Project\Event;

final class PeremenayaUdalenaIzProekta
{
    public function __construct(
        public readonly string $projectId,
        public readonly string $code,
    ) { }
}
