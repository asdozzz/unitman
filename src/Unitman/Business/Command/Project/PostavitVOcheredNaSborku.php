<?php

namespace App\Unitman\Business\Command\Project;

use App\Utils\Converter\JsonBodySerializableInterface;

final class PostavitVOcheredNaSborku implements JsonBodySerializableInterface
{
    public function __construct(
        public readonly string $id,
    )
    {
    }
}
