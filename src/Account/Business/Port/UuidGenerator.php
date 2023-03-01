<?php

namespace App\Account\Business\Port;

use Symfony\Component\Uid\Uuid;

interface UuidGenerator
{
    public function make(): string;
}
