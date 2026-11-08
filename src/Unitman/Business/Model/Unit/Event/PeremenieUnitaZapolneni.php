<?php

namespace App\Unitman\Business\Model\Unit\Event;

final class PeremenieUnitaZapolneni
{
    /**
     * @param array<int, array<string, mixed>> $svyazi связи переменных с выбранными юнитами
     *                                                (PeremennayaSvyaz::toArray())
     */
    public function __construct(
        public readonly string $unitId,
        public readonly array $values,
        public readonly array $stateAsArray,
        public readonly array $volumes = [],
        public readonly array $svyazi = []
    )
    {
    }
}
