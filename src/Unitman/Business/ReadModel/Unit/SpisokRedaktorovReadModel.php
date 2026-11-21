<?php

declare(strict_types=1);

namespace App\Unitman\Business\ReadModel\Unit;

/**
 * Список редакторов юнита с состоянием их личных токенов.
 */
final class SpisokRedaktorovReadModel
{
    /**
     * @param RedaktorUnitaReadModel[] $redaktory
     */
    public function __construct(
        public readonly string $unitId,
        public readonly bool $redaktirovanieVklucheno,
        public readonly array $redaktory,
    )
    {
    }
}
