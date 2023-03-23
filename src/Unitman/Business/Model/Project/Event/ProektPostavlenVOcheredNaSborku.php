<?php

namespace App\Unitman\Business\Model\Project\Event;

final class ProektPostavlenVOcheredNaSborku
{
    public function __construct(
        public readonly string $id,
        public readonly string $jobId
    ) {}
}
