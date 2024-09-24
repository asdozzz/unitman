<?php

namespace App\Unitman\Business\Command\Unit;

use App\Utils\Converter\JsonBodySerializableInterface;

readonly class GetUnitRunnerJobSteps implements JsonBodySerializableInterface
{
    public function __construct(public string $id)
    {
    }

}
