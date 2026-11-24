<?php

namespace App\Runner\Infra\Workflow;

use App\Runner\Business\Command\NachatSborkuUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatSbrokiUnita;
use Temporal\Activity\ActivityOptions;
use Temporal\Workflow;
use Temporal\Workflow\ReturnType;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
final class NachatSborkuUnitaWorkflow
{
    /**
     * @psalm-suppress MissingReturnType
     * */
    #[WorkflowMethod]
    #[ReturnType(ResultatSbrokiUnita::class)]
    public function execute(NachatSborkuUnita $command, string $taskQueue = '')
    {
        if ($taskQueue === '') {
            throw new \InvalidArgumentException('taskQueue обязателен для запуска workflow');
        }

        return yield Workflow::executeActivity(
            'NachatSborkuUnitaActivity',
            [$command],
            ActivityOptions::new()
                ->withStartToCloseTimeout(60*60)
                ->withTaskQueue($taskQueue)
        );
    }
}
