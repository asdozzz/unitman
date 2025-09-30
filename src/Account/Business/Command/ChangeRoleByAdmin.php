<?php

namespace App\Account\Business\Command;

use App\Utils\Converter\JsonBodySerializableInterface;

readonly class ChangeRoleByAdmin implements JsonBodySerializableInterface
{
    public function __construct(
        public string $accountId,
        public string $newRole
    ) {}
}
