<?php

declare(strict_types=1);

namespace App\Skill\Infra\Adapter;

use App\Skill\Business\Port\UuidGenerator;
use Ramsey\Uuid\Uuid;

final class RamseyUuidGenerator implements UuidGenerator
{
    public function makeGuid(): string
    {
        return Uuid::uuid4()->toString();
    }
}
