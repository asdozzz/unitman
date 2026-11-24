<?php

declare(strict_types=1);

namespace App\Runner\Infra\Workflow;

use App\Runner\Business\Command\Workspace\ResultatZapuskaWorkspace;
use App\Runner\Business\Command\Workspace\ZapustitWorkspace;
use Temporal\Activity\ActivityOptions;
use Temporal\Workflow;
use Temporal\Workflow\ReturnType;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
final class ZapustitWorkspaceWorkflow
{
    public const ACTIVITY = 'ZapustitWorkspaceActivity';

    /**
     * @psalm-suppress MissingReturnType
     */
    #[WorkflowMethod(name: 'ZapustitWorkspace')]
    #[ReturnType(ResultatZapuskaWorkspace::class)]
    public function execute(ZapustitWorkspace $command, string $taskQueue = '')
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
