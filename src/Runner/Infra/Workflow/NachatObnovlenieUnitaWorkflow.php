<?php

namespace App\Runner\Infra\Workflow;

use App\Runner\Business\Command\NachatObnovlenieUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatObnovleniyaUnita;
use Temporal\Activity\ActivityOptions;
use Temporal\Workflow;
use Temporal\Workflow\ReturnType;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
final class NachatObnovlenieUnitaWorkflow
{
    /**
     * @psalm-suppress MissingReturnType
     * */
    #[WorkflowMethod]
    #[ReturnType(ResultatObnovleniyaUnita::class)]
    public function execute(NachatObnovlenieUnita $command, string $taskQueue = '')
    {
        if ($taskQueue === '') {
            throw new \InvalidArgumentException('taskQueue обязателен для запуска workflow');
        }

        return yield Workflow::executeActivity(
            'NachatObnovlenieUnitaActivity',
            [$command],
            ActivityOptions::new()
                ->withStartToCloseTimeout(60*60)
                ->withTaskQueue($taskQueue)
        );
    }
}
