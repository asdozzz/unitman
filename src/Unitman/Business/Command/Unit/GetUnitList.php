<?php

namespace App\Unitman\Business\Command\Unit;

use App\Unitman\Business\Command\Unit\GetUnitList\GetUnitListFilter;
use App\Utils\Converter\JsonBodySerializableInterface;

final class GetUnitList implements JsonBodySerializableInterface
{
    public function __construct(
        public readonly GetUnitListFilter $filter = new GetUnitListFilter(),
        public readonly int $limit = 100,
        public readonly int $offset = 0
    )
    {
    }
}
