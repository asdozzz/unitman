<?php

namespace App\Unitman\Business\Port;

interface CanGeneateGuid
{
    /**
     * @return non-empty-string
     * */
    function makeGuid(): string;
}
