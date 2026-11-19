<?php

declare(strict_types=1);

namespace App\Runner\Business\Port;

/**
 * Порт Runner к Unitman: обновление состояния контейнера юнита
 * по уведомлению раннера.
 */
interface UmeetObnovlyatSostoyanieKonteyneraUnita
{
    public function obnovit(
        string $unitId,
        bool $mismatch,
        ?string $expectedState,
        ?string $actualState,
        ?int $detectedAt,
    ): void;
}
