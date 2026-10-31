<?php

namespace App\Account\Business\Command;

use App\Utils\Converter\JsonBodySerializableInterface;

final class ObnovitToken implements JsonBodySerializableInterface
{
    public function __construct(
        public readonly string $refreshToken
    )
    {
    }
}
