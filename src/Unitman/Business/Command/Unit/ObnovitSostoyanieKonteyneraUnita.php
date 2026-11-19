<?php

declare(strict_types=1);

namespace App\Unitman\Business\Command\Unit;

final class ObnovitSostoyanieKonteyneraUnita
{
    public function __construct(
        public readonly string $unitId,
        public readonly bool $mismatch,
        public readonly ?string $expectedState = null,
        public readonly ?string $actualState = null,
        public readonly ?int $detectedAt = null,
    ) {
    }
}
