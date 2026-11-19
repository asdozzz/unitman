<?php

declare(strict_types=1);

namespace App\Runner\Business\Command;

/**
 * Уведомление раннера о расхождении/изменении состояния контейнера юнита.
 *
 * `eventType` — тип события раннера (например, `mismatch`).
 */
final class UvedomlenieOSostoyaniiKonteynera
{
    public function __construct(
        public readonly string $unitId,
        public readonly string $projectId,
        public readonly string $unitName,
        public readonly string $projectName,
        public readonly string $expectedState,
        public readonly string $actualState,
        public readonly string $eventType,
        public readonly int $detectedAt,
    ) {
    }
}
