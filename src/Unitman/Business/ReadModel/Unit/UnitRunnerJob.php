<?php

namespace App\Unitman\Business\ReadModel\Unit;

use App\Unitman\Business\Model\Unit\Runner\RunnerJobStep;

final class UnitRunnerJob extends UnitRunnerJobWithoutSteps
{
    const SBORKA = 'SBORKA';
    const OBNOVLENIE = 'OBNOVLENIE';
    const PODGOTOVKA = 'PODGOTOVKA';
    const SBROS_PODGOTOVKI = 'SBROS_PODGOTOVKI';
    const ZAPUSK = 'ZAPUSK';
    const OSTANOVKA = 'OSTANOVKA';
    const UDALENIE = 'UDALENIE';

    const IZMENENIYE_VETKI = 'IZMENENIYE_VETKI';
    /**
     * @var RunnerJobStep[]
     */
    public readonly array $steps;

    /**
     * @param string $id
     * @param string $unitId
     * @param string $jobType
     * @param bool $success
     * @param array<RunnerJobStep> $steps
     */
    public function __construct(
        string $id,
        string $unitId,
        string $jobType,
        bool $success,
        array $steps
    )
    {
        parent::__construct($id, $unitId, $jobType, $success);
        $this->steps = $steps;
    }

}
