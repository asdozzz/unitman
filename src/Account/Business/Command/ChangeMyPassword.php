<?php

namespace App\Account\Business\Command;

use App\Utils\Converter\JsonBodySerializableInterface;

final class ChangeMyPassword implements JsonBodySerializableInterface
{
    public function __construct(
        public readonly string $newPassword
    )
    {
    }

}
