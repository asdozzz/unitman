<?php

namespace App\Unitman\Business\Model\Project\Event;

final class PeremenayaDobavlenaVProekt
{
    public function __construct(
        public readonly string $projectId,
        public readonly string $tip,
        public readonly string $code,
        public readonly string $value,
    ) { }

}
