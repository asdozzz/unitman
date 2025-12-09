<?php

namespace App\Unitman\Business\Command\Project\Webhook;

use App\Utils\Converter\JsonBodySerializableInterface;

final class IzmenitUrlWebhooka implements JsonBodySerializableInterface
{
    public function __construct(
        public string $id,
        public string $newUrl,
    )
    {
    }
}
