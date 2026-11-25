<?php

declare(strict_types=1);

namespace App\Notification\Infra\Adapter;

use App\Notification\Business\Port\CanGenerateGuid;
use Ramsey\Uuid\Uuid;

final class RamseyGuidGenerator implements CanGenerateGuid
{
    public function makeGuid(): string
    {
        return Uuid::uuid4()->toString();
    }
}
