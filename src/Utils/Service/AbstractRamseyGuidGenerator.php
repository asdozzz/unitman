<?php

namespace App\Utils\Service;

use Ramsey\Uuid\Uuid;

abstract class AbstractRamseyGuidGenerator
{
    /**
     * @return non-empty-string
     * */
    function makeGuid(): string
    {
        return Uuid::uuid7()->toString();
    }
}
