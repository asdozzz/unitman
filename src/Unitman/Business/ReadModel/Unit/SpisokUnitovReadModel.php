<?php

namespace App\Unitman\Business\ReadModel\Unit;

final class SpisokUnitovReadModel
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $projectId,
        public readonly string $branch,
        public readonly string $state,
        public readonly string $textOtRunnera,
        public readonly bool $waitResultFromRunner,
        public readonly string $config,
        public readonly string $configValues,
    )
    {
    }

    function copyAndUpdateData(array $props): static
    {
        $id = $this->id;
        $name = $this->name;
        $projectId = $this->projectId;
        $branch = $this->branch;

        $state = $props['state']??$this->state;
        $textOtRunnera = $props['textOtRunnera']??$this->textOtRunnera;
        $waitResultFromRunner = $props['waitResultFromRunner']??$this->waitResultFromRunner;
        $config = $props['config']??$this->config;
        $configValues = $props['configValues']??$this->configValues;

        return new static(
            $id,
            $name,
            $projectId,
            $branch,
            $state,
            $textOtRunnera,
            $waitResultFromRunner,
            $config,
            $configValues,
        );
    }
}
