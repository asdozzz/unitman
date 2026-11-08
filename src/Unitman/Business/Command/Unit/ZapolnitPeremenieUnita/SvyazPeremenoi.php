<?php

declare(strict_types=1);

namespace App\Unitman\Business\Command\Unit\ZapolnitPeremenieUnita;

/**
 * Связь переменной конфига с юнитом, выбранным через хелпер переменной.
 */
final class SvyazPeremenoi
{
    public function __construct(
        public readonly string $variableId,
        public readonly string $targetUnitId,
    )
    {
    }
}
