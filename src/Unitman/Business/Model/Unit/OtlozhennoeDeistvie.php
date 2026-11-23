<?php

declare(strict_types=1);

namespace App\Unitman\Business\Model\Unit;

use App\Unitman\Business\Model\SobitieIzHranilisha\TipSobitiya;

/**
 * Отложенное намерение применить событие Git-вебхука к юниту.
 *
 * Событие нельзя применить немедленно, пока над юнитом идёт активити или ход
 * OpenCode, но и терять его нельзя: job вебхука ретраится ограниченное число
 * раз. Поэтому намерение живёт в таблице до первого освобождения юнита.
 */
final class OtlozhennoeDeistvie
{
    const STATUS_READY = 'ready';
    const STATUS_HELD_AUTO_OBNOVLENIE = 'held_autoobnovlenie';

    /**
     * @param array $payload данные события: ветка, unixtime и т.п.
     */
    public function __construct(
        public readonly string $unitId,
        public readonly string $projectId,
        public readonly TipSobitiya $tipSobitiya,
        public readonly array $payload,
        public readonly int $attempts,
        public readonly \DateTimeImmutable $nextAttemptAt,
        public readonly ?int $id = null,
        public readonly ?string $lastError = null,
        public readonly string $status = self::STATUS_READY,
    )
    {
    }
}
