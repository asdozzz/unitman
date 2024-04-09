<?php

namespace App\Unitman\Business\ReadModel\Unit;

use App\Unitman\Business\Model\Unit\Runner\RunnerJobStep;

final class UnitRunnerJob
{
    const SBORKA = 'SBORKA';
    const OBNOVLENIE = 'OBNOVLENIE';
    const PODGOTOVKA = 'PODGOTOVKA';
    const SBROS_PODGOTOVKI = 'SBROS_PODGOTOVKI';
    const ZAPUSK = 'ZAPUSK';
    const OSTANOVKA = 'OSTANOVKA';
    const UDALENIE = 'UDALENIE';
    /**
     * @param string $id
     * @param string $unitId
     * @param string $jobType
     * @param bool $success
     * @param array<RunnerJobStep> $steps
     */
    public function __construct(
        public readonly string $id,
        public readonly string $unitId,
        public readonly string $jobType,
        public readonly bool $success,
        public readonly array $steps
    )
    {
    }

}
