<?php

namespace App\Unitman\Business\Command\Project;

use App\Utils\Converter\JsonBodySerializableInterface;

final class GetProjectList implements JsonBodySerializableInterface
{
    public function __construct(public readonly int $limit = 10, public readonly int $offset = 0)
    {
    }
}
