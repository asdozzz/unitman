<?php

namespace App\Unitman\Business\ReadModel\Project;

use App\Unitman\Business\Model\Unit\Runner\RunnerJobStep;

final class ProjectRunnerJob extends ProjectRunnerJobWithoutSteps
{
    const SBORKA_PROEKTA = 'SBORKA_PROEKTA';
    const UDALENIE_PROEKTA = 'UDALENIE_PROEKTA';
    const OCHISTKA_PROEKTA = 'OCHISTKA_PROEKTA';
    /**
     * @var RunnerJobStep[]
     */
    public readonly array $steps;

    public function __construct(string $id, string $projectId, string $jobType, bool $success, array $steps)
    {
        parent::__construct($id, $projectId, $jobType, $success);
        $this->steps = $steps;
    }

}
