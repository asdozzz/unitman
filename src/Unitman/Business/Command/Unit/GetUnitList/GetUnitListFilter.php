<?php

namespace App\Unitman\Business\Command\Unit\GetUnitList;

final class GetUnitListFilter
{
    public function __construct(
        public readonly bool $onlyMine = false,
        public readonly ?string $name = null,
        public readonly ?string $branch = null,
        public readonly ?string $projectId = null,
        public readonly ?string $unitId = null,
    )
    {
    }

}
