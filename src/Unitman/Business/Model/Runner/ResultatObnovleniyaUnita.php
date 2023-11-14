<?php

namespace App\Unitman\Business\Model\Runner;

final class ResultatObnovleniyaUnita
{
    public function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly ?string $config = null,
    )
    {
    }
}
