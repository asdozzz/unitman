<?php

namespace App\Account\Infra\Adapter;

use App\Account\Business\Port\UuidGenerator;
use Ramsey\Uuid\Uuid;

final class RamseyUuidGenerator implements UuidGenerator
{

    public function make(): string
    {
        $uuid = Uuid::uuid7();
        return $uuid->toString();
    }
}
