<?php

namespace App\Runner\Business\Command;

use App\Utils\Converter\JsonBodySerializableInterface;

final class SaveStepsCommand implements JsonBodySerializableInterface
{
    public function __construct(
        public readonly string $responseId,
        public readonly string $stepsContent,
    )
    {
    }

}
