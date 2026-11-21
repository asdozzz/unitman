<?php

declare(strict_types=1);

namespace App\Unitman\Business\Model\Unit\Event;

/**
 * Режим редактирования файлов юнита включён.
 */
final class RedaktirovanieUnitaVklucheno
{
    public function __construct(
        public readonly string $unitId,
    )
    {
    }
}
