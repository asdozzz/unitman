<?php

declare(strict_types=1);

namespace App\Runner\Infra\Workflow;

use App\Runner\Business\Command\Workspace\ObnovitWorkspace;
use App\Runner\Business\Command\Workspace\ResultatObnovleniyaWorkspace;
use Temporal\Activity\ActivityOptions;
use Temporal\Workflow;
use Temporal\Workflow\ReturnType;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
final class ObnovitWorkspaceWorkflow
{
    public const ACTIVITY = 'ObnovitWorkspaceActivity';

    /**
     * @psalm-suppress MissingReturnType
     */
    #[WorkflowMethod(name: 'ObnovitWorkspace')]
    #[ReturnType(ResultatObnovleniyaWorkspace::class)]
    public function execute(ObnovitWorkspace $command, string $taskQueue = '')
    {
        if ($taskQueue === '') {
            throw new \InvalidArgumentException('taskQueue обязателен для запуска workflow');
        }

        return yield Workflow::executeActivity(
            self::ACTIVITY,
            [$command],
            ActivityOptions::new()
                ->withStartToCloseTimeout(60 * 30)
                ->withTaskQueue($taskQueue)
        );
    }
}
