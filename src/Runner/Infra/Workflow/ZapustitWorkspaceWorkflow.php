<?php

declare(strict_types=1);

namespace App\Runner\Infra\Workflow;

use App\App\Infra\Workflow\WorkflowClientFactory;
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
    public const TASK_QUEUE = WorkflowClientFactory::runnerQueueName;

    /**
     * @psalm-suppress MissingReturnType
     */
    #[WorkflowMethod(name: 'ZapustitWorkspace')]
    #[ReturnType(ResultatZapuskaWorkspace::class)]
    public function execute(ZapustitWorkspace $command)
    {
        return yield Workflow::executeActivity(
            self::ACTIVITY,
            [$command],
            ActivityOptions::new()
                ->withStartToCloseTimeout(60 * 30)
                ->withTaskQueue(self::TASK_QUEUE)
        );
    }
}
