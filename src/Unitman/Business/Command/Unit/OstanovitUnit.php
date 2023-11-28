<?php

namespace App\Unitman\Business\Command\Unit;

use App\Utils\Converter\JsonBodySerializableInterface;

final class OstanovitUnit implements JsonBodySerializableInterface
{
    public function __construct(
        public readonly string $id
    )
    {
    }
}
