<?php

namespace App\Runner\Infra\Workflow;
use App\App\Infra\Workflow\WorkflowClientFactory;
use App\Runner\Business\Command\InitProjectCommand;
use App\Runner\Business\Model\GolangRunner\Project\InitProjectResult;
use Temporal\Activity\ActivityOptions;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
final class InitProjectWorkflow
{
    /**
     * @psalm-suppress MissingReturnType
     * */
    #[WorkflowMethod(name: "InitProject")]
    #[Workflow\ReturnType(InitProjectResult::class)]
    public function initProject(InitProjectCommand $command)
    {
        return yield Workflow::executeActivity(
            'InitProjectActivity',
            [$command],
            ActivityOptions::new()
                ->withStartToCloseTimeout(30)
                ->withTaskQueue(WorkflowClientFactory::runnerQueueName)
        );
    }
}
