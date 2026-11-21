<?php

declare(strict_types=1);

namespace App\Unitman\Business\Model\Unit\Event;

/**
 * Режим редактирования файлов юнита выключен.
 */
final class RedaktirovanieUnitaVyklucheno
{
    public function __construct(
        public readonly string $unitId,
    )
    {
    }
}
