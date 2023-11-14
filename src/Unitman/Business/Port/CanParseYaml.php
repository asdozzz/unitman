<?php

namespace App\Unitman\Business\Port;

interface CanParseYaml
{
    function parse(?string $content): array;
}
