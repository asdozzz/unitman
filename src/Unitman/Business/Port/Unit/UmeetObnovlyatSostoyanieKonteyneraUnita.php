<?php

declare(strict_types=1);

namespace App\Unitman\Business\Port\Unit;

interface UmeetObnovlyatSostoyanieKonteyneraUnita
{
    public function obnovitSostoyanieKonteynera(
        string $unitId,
        bool $mismatch,
        ?string $expectedState,
        ?string $actualState,
        ?int $detectedAt,
    ): bool;
}
