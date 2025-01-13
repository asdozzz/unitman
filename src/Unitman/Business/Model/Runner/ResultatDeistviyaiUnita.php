<?php

namespace App\Unitman\Business\Model\Runner;

final class ResultatDeistviyaiUnita
{
    public function __construct(
        public readonly bool $success,
        public readonly array $steps
    )
    {
    }
}
