<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\GetUnitRunnerJobs;
use App\Unitman\Business\Port\Unit\CanGetUnitRunnerJobs;

final class GetUnitRunnerJobsQuery
{
    public function __construct(private CanGetUnitRunnerJobs $canGetUnitRunnerJobs)
    {
    }

    function handle(GetUnitRunnerJobs $command): array
    {
        return $this->canGetUnitRunnerJobs->findAllRunnerJobsByUnitId($command->id);
    }
}
