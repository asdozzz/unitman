<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\PoluchitShagiZadachiUnita;
use App\Unitman\Business\Port\Unit\UmeetPoluchatProzesiUnitaPoId;
use App\Unitman\Business\ReadModel\Unit\ProzesUnita;

final class GetUnitRunnerJobStepsQuery
{
    public function __construct(private UmeetPoluchatProzesiUnitaPoId $canGetUnitRunnerJobs)
    {
    }

    function handle(PoluchitShagiZadachiUnita $command): array
    {
        return $this->canGetUnitRunnerJobs->poluchitShagiZadachiPoId($command->prozesId, $command->zadachaId);
    }
}
