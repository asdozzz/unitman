<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\GetUnitRunnerJobs;
use App\Unitman\Business\Port\Unit\UmeetPoluchatProzesiUnitaPoId;

final class GetUnitRunnerJobsQuery
{
    public function __construct(private UmeetPoluchatProzesiUnitaPoId $canGetUnitRunnerJobs)
    {
    }

    function handle(GetUnitRunnerJobs $command): array
    {
        return $this->canGetUnitRunnerJobs->poluchitProzesiPoIdUnita($command->id);
    }
}
