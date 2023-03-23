<?php

namespace App\Utils\Service;

use Ramsey\Uuid\Uuid;

abstract class AbstractRamseyGuidGenerator
{
    function makeGuid(): string
    {
        return Uuid::uuid7()->toString();
    }
}
