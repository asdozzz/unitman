<?php

namespace App\Unitman\Business\ReadModel\Unit\ProzesUnita;

final class ShagZadachiUnita
{
    public function __construct(
        public readonly string $command,
        public readonly string $response,
        public readonly bool $success,
        public readonly int $unixtime)
    {
    }
}
