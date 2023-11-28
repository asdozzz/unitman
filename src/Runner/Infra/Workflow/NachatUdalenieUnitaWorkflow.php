<?php

namespace App\Runner\Infra\Workflow;

use App\App\Infra\Workflow\WorkflowClientFactory;
use App\Runner\Business\Command\NachatUdalenieUnita;
use App\Runner\Business\Model\ResultatUdaleniyaUnita;
use Temporal\Activity\ActivityOptions;
use Temporal\Workflow;
use Temporal\Workflow\ReturnType;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
final class NachatUdalenieUnitaWorkflow
{
    #[WorkflowMethod]
    #[ReturnType(ResultatUdaleniyaUnita::class)]
    public function execute(NachatUdalenieUnita $command)
    {
        return yield Workflow::executeActivity(
            'NachatUdalenieUnitaActivity',
            [$command],
            ActivityOptions::new()
                ->withStartToCloseTimeout(60*60)
                ->withTaskQueue(WorkflowClientFactory::runnerQueueName)
        );
    }
}
