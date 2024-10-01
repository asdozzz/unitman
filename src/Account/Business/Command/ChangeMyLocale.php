<?php

namespace App\Account\Business\Command;

use App\Utils\Converter\JsonBodySerializableInterface;

readonly class ChangeMyLocale implements JsonBodySerializableInterface
{
    public function __construct(public string $locale)
    {
    }

}
