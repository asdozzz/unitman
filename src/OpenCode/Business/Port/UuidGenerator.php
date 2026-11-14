<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Port;

interface UuidGenerator
{
    public function makeGuid(): string;
}