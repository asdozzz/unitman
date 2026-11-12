<?php

namespace App\Unitman\Business\Port;

interface CanGenerateGuid
{
    /**
     * @return non-empty-string
     * */
    function makeGuid(): string;
}
