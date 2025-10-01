<?php

namespace App\Unitman\Business\Command\Unit;

use App\Utils\Converter\JsonBodySerializableInterface;

final class NaitiDubliUnita implements JsonBodySerializableInterface
{
    public function __construct(
        public readonly string $projectId,
        public readonly string $branch
    )
    {
    }

}
