<?php

namespace App\Unitman\Business\Model\Project\Event;

final class ZnacheniePeremnoiProektaIzmeneno
{
    public function __construct(
        public readonly string $projectId,
        public readonly string $code,
        public readonly string $newValue,
    ) { }
}
