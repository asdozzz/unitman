<?php

namespace App\Unitman\Business\Model\Runner;

final class ResultatOstanovkiUnita
{
    public function __construct(
        public readonly bool $success,
        public readonly string $message
    )
    {
    }
}
