<?php

declare(strict_types=1);

namespace App\BackgroundJob\Infra\Service;

abstract class AbstractBackgroundJob implements BackgroundJobInterface
{
    public function getDelay(): int
    {
        return 1;
    }

    public function getProcessNum(): int
    {
        return 1;
    }
}
