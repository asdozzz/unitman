<?php

namespace App\Account\Business\Command;

use App\Utils\Converter\JsonBodySerializableInterface;

final class ChangeMyNickname implements JsonBodySerializableInterface
{
    public function __construct(
        public readonly string $newNickname
    ) {}
}
