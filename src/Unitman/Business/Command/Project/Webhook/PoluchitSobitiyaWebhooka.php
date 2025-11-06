<?php

namespace App\Unitman\Business\Command\Project\Webhook;

use App\Utils\Converter\JsonBodySerializableInterface;

readonly class PoluchitSobitiyaWebhooka implements JsonBodySerializableInterface
{
    public function __construct(
        public string $webhookId,
        public int $limit,
        public int $offset,
    )
    {
    }

}
