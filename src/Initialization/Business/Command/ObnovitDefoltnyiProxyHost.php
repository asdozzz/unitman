<?php

namespace App\Initialization\Business\Command;

readonly class ObnovitDefoltnyiProxyHost
{
    public function __construct(public ?string $value)
    {
    }
}
