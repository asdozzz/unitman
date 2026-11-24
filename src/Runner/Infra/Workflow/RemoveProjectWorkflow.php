<?php

namespace App\Runner\Infra\Workflow;
use App\Runner\Business\Command\RemoveProjectCommand;
use App\Runner\Business\Model\GolangRunner\Project\RemoveProjectResult;
use Temporal\Activity\ActivityOptions;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
final class RemoveProjectWorkflow
{
    /**
     * @psalm-suppress MissingReturnType
     * */
    #[WorkflowMethod(name: "RemoveProject")]
    #[Workflow\ReturnType(RemoveProjectResult::class)]
    public function removeProject(RemoveProjectCommand $command, string $taskQueue = '')
    {
        if ($taskQueue === '') {
            throw new \InvalidArgumentException('taskQueue обязателен для запуска workflow');
        }

        return yield Workflow::executeActivity(
            'RemoveProjectActivity',
            [$command],
            ActivityOptions::new()
                ->withStartToCloseTimeout(3)
                ->withTaskQueue($taskQueue)
        );
    }
}
