<?php

declare(strict_types=1);

namespace App\Unitman\Business\ReadModel\Unit;

/**
 * Состояние одного прикреплённого редактора юнита.
 */
final class RedaktorUnitaReadModel
{
    public function __construct(
        public readonly string $userId,
        public readonly string $imya,
        public readonly bool $imeetTokenNaProekt,
    )
    {
    }
}
