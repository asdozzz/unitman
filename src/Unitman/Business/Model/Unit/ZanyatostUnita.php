<?php

declare(strict_types=1);

namespace App\Unitman\Business\Model\Unit;

/**
 * Активный захват юнита внешним владельцем.
 *
 * `vladelec` — непрозрачный для Unitman токен вида `opencode:<turnId>`.
 * Unitman не разбирает его и не импортирует модуль владельца: единственное,
 * что нужно ядру, — уметь сравнить токен при освобождении.
 */
final class ZanyatostUnita
{
    public function __construct(
        public readonly string $unitId,
        public readonly string $vladelec,
        public readonly TipZanyatosti $tip,
        public readonly \DateTimeImmutable $zahvachenoV,
        public readonly \DateTimeImmutable $istekaetV,
    )
    {
    }
}
