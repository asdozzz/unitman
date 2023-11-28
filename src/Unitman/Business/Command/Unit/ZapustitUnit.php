<?php

namespace App\Unitman\Business\Command\Unit;

use App\Utils\Converter\JsonBodySerializableInterface;

final class ZapustitUnit implements JsonBodySerializableInterface
{
    public function __construct(
        public readonly string $id
    )
    {
    }
}
