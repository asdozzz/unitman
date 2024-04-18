<?php

namespace App\Runner\Business\Port;

interface CanGenerateGuid
{
    function makeGuid(): string;
}
