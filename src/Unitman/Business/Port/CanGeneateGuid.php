<?php

namespace App\Unitman\Business\Port;

interface CanGeneateGuid
{
    function makeGuid(): string;
}
