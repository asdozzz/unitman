<?php

declare(strict_types=1);

namespace App\Unitman\Business\Port\Unit;

/**
 * Хранилище связей «юнит → юнит, выбранный через хелпер переменной».
 */
interface UmeetPoluchatSvyaziUnitov
{
    /**
     * Связи, где юнит является источником.
     *
     * @return array<int, array{variableId: string, targetUnitId: string, projectId: string}>
     */
    public function poluchitPoIdUnita(string $unitId): array;

    /**
     * Id юнитов, связанных с переданными в обе стороны
     * (источник → цель и цель → источник).
     *
     * @param string[] $unitIds
     * @return string[]
     */
    public function poluchitSvyazannieIdUnitov(array $unitIds): array;
}
