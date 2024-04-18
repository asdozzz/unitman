<?php

namespace App\Runner\Infra\Workflow;

use App\App\Infra\Workflow\WorkflowClientFactory;
use App\Runner\Business\Command\NachatPodgotovkuUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatPodgotovkiUnita;
use Temporal\Activity\ActivityOptions;
use Temporal\Workflow;
use Temporal\Workflow\ReturnType;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
final class NachatPodgotovkuUnitaWorkflow
{
    /**
     * @psalm-suppress MissingReturnType
     * */
    #[WorkflowMethod]
    #[ReturnType(ResultatPodgotovkiUnita::class)]
    public function execute(NachatPodgotovkuUnita $command)
    {
        return yield Workflow::executeActivity(
            'NachatPodgotovkuUnitaActivity',
            [$command],
            ActivityOptions::new()
                ->withStartToCloseTimeout(60*60)
                ->withTaskQueue(WorkflowClientFactory::runnerQueueName)
        );
    }
}
