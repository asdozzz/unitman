<?php

namespace App\Unitman\Business\Model\Runner;

final class ResultatObnovleniyaUnita
{
    public function __construct(
        public readonly bool $success,
        public readonly array $steps,
        public readonly ?string $config = null,
    )
    {
    }
}
