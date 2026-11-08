<?php

namespace App\Unitman\Business\Command\Unit\GetUnitList;

final class GetUnitListFilter
{
    /**
     * @param string[] $unitIds вернуть переданные юниты и связанные с ними
     *        (связи через хелпер переменной, в обе стороны)
     */
    public function __construct(
        public readonly bool $onlyMine = false,
        public readonly ?string $name = null,
        public readonly ?string $branch = null,
        public readonly ?string $projectId = null,
        public readonly ?string $unitId = null,
        /** @var string[] */
        public readonly array $unitIds = [],
    )
    {
    }

}
