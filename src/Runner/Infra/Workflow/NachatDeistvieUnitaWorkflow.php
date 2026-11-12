<?php

namespace App\Runner\Infra\Workflow;

use App\App\Infra\Workflow\WorkflowClientFactory;
use App\Runner\Business\Command\NachatDeistvieUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatDeistviyaUnita;
use Temporal\Activity\ActivityOptions;
use Temporal\Workflow;
use Temporal\Workflow\ReturnType;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
final class NachatDeistvieUnitaWorkflow
{
    /**
     * @psalm-suppress MissingReturnType
     * */
    #[WorkflowMethod]
    #[ReturnType(ResultatDeistviyaUnita::class)]
    public function execute(NachatDeistvieUnita $command)
    {
        return yield Workflow::executeActivity(
            'NachatDeistvieUnitaActivity',
            [$command],
            ActivityOptions::new()
                ->withStartToCloseTimeout(60*60)
                ->withTaskQueue(WorkflowClientFactory::runnerQueueName)
        );
    }
}
