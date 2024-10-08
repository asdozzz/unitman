<?php

namespace App\Unitman\Business\Model\Project\Event;

final class OshibkaOchistkiProektaUstanovlena
{
    public function __construct(
        public readonly string $id,
        public readonly array $steps
    )
    {
    }
}
