<?php

namespace App\Account\Business\Command;

use App\Utils\Converter\JsonBodySerializableInterface;

final class ChangeEmailByAdmin implements JsonBodySerializableInterface
{
    public function __construct(
        public readonly string $accountId,
        public readonly string $newEmail
    ) {}
}
