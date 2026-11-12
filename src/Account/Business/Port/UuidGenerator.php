<?php

namespace App\Account\Business\Port;


interface UuidGenerator
{
    public function makeGuid(): string;
}
