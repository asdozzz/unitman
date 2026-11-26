<?php

declare(strict_types=1);

namespace App\Account\Business\Command;

use App\Utils\Converter\JsonBodySerializableInterface;

final class ActivateAccountByAdmin implements JsonBodySerializableInterface
{
    public function __construct(public readonly string $accountId)
    {
    }
}
