<?php

declare(strict_types=1);

namespace App\Skill\Tests\Support;

use App\Skill\Business\Port\UuidGenerator;

final class FixedUuidGenerator implements UuidGenerator
{
    private int $nomer = 0;

    public function __construct(private readonly string $prefix = 'skill-')
    {
    }

    public function makeGuid(): string
    {
        ++$this->nomer;

        return $this->prefix . $this->nomer;
    }
}
