<?php

namespace App\Runner\Infra\Workflow;
use App\App\Infra\Workflow\WorkflowClientFactory;
use App\Runner\Business\Command\RemoveProjectCommand;
use App\Runner\Business\Model\RemoveProjectResult;
use Temporal\Activity\ActivityOptions;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
final class RemoveProjectWorkflow
{
    #[WorkflowMethod(name: "RemoveProject")]
    #[Workflow\ReturnType(RemoveProjectResult::class)]
    public function removeProject(RemoveProjectCommand $command)
    {
        return yield Workflow::executeActivity(
            'RemoveProjectActivity',
            [$command],
            ActivityOptions::new()
                ->withStartToCloseTimeout(3)
                ->withTaskQueue(WorkflowClientFactory::runnerQueueName)
        );
    }
}
