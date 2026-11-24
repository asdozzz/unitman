<?php

declare(strict_types=1);

namespace App\Runner\Infra\Workflow;

use App\Runner\Business\Command\Workspace\ResultatUdaleniyaWorkspace;
use App\Runner\Business\Command\Workspace\UdalitWorkspace;
use Temporal\Activity\ActivityOptions;
use Temporal\Workflow;
use Temporal\Workflow\ReturnType;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
final class UdalitWorkspaceWorkflow
{
    public const ACTIVITY = 'UdalitWorkspaceActivity';

    /**
     * @psalm-suppress MissingReturnType
     */
    #[WorkflowMethod(name: 'UdalitWorkspace')]
    #[ReturnType(ResultatUdaleniyaWorkspace::class)]
    public function execute(UdalitWorkspace $command, string $taskQueue = '')
    {
        if ($taskQueue === '') {
            throw new \InvalidArgumentException('taskQueue обязателен для запуска workflow');
        }

        return yield Workflow::executeActivity(
            self::ACTIVITY,
            [$command],
            ActivityOptions::new()
                ->withStartToCloseTimeout(60 * 10)
                ->withTaskQueue($taskQueue)
        );
    }
}
