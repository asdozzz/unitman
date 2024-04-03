<?php

namespace App\Unitman\Business\Model\Runner;

final class ResponseToBuildProject
{
    public function __construct(
        public readonly bool $success,
        public readonly array $steps
    )
    {
    }

}
