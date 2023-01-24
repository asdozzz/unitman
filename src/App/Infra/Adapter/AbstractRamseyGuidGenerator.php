<?php

namespace App\App\Infra\Adapter;

use Ramsey\Uuid\Uuid;

abstract class AbstractRamseyGuidGenerator
{
    function makeGuid(): string
    {
        return Uuid::uuid4()->toString();
    }
}
