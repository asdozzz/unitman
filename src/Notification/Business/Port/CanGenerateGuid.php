<?php

declare(strict_types=1);

namespace App\Notification\Business\Port;

interface CanGenerateGuid
{
    public function makeGuid(): string;
}
