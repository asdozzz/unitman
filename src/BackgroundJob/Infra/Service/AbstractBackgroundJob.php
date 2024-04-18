<?php

namespace App\BackgroundJob\Infra\Service;

abstract class AbstractBackgroundJob implements BackgroundJobInterface
{
    function getDelay(): int
    {
        return 1;
    }
}
