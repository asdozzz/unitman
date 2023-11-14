<?php

namespace App\Runner\Infra\Workflow;
use App\App\Infra\Workflow\WorkflowClientFactory;
use App\Runner\Business\Command\InitProjectCommand;
use App\Runner\Business\Model\InitProjectResult;
use Temporal\Activity\ActivityOptions;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
final class InitProjectWorkflow
{
    #[WorkflowMethod(name: "InitProject")]
    #[Workflow\ReturnType(InitProjectResult::class)]
    public function initProject(InitProjectCommand $command)
    {
        return yield Workflow::executeActivity(
            'InitProjectActivity',
            [$command],
            ActivityOptions::new()
                ->withStartToCloseTimeout(3)
                ->withTaskQueue(WorkflowClientFactory::runnerQueueName)
        );
    }
}
