<?php

namespace App\Unitman\Infra\Temporal\Workflow;

use App\Unitman\Business\Model\Runner\JobId;
use App\Unitman\Business\Port\Unit\UmeetObnovlyatKodUnitaPosleZapuska;
use Carbon\CarbonInterval;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowOptions;

final class ProzesObnovlenieKodaPosleZapuskaWorkflowFactory implements UmeetObnovlyatKodUnitaPosleZapuska
{
    public function __construct(private WorkflowClient $workflowClient)
    {
    }

    function obnovitKodUnita(string $unitId): JobId
    {
        $workflow = $this->workflowClient->newWorkflowStub(
            ProzesObnovlenieKodaPosleZapuskaWorkflow::class,
            WorkflowOptions::new()
                ->withTaskQueue(\App\App\Infra\Workflow\WorkflowClientFactory::monoQueueName)
                ->withWorkflowExecutionTimeout(CarbonInterval::minute(10))
        );

        $run = $this->workflowClient->start($workflow, $unitId);
        $jobId = $run->getExecution()->getID();
        return new JobId($jobId);
    }
}
