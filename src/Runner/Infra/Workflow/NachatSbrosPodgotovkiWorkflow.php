<?php

namespace App\Runner\Infra\Workflow;

use App\App\Infra\Workflow\WorkflowClientFactory;
use App\Runner\Business\Command\NachatSbrosPodgotovkiUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatSbrosaPodgotovkiUnita;
use Temporal\Activity\ActivityOptions;
use Temporal\Workflow;
use Temporal\Workflow\ReturnType;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
final class NachatSbrosPodgotovkiWorkflow
{
    /**
     * @psalm-suppress MissingReturnType
     * */
    #[WorkflowMethod]
    #[ReturnType(ResultatSbrosaPodgotovkiUnita::class)]
    public function execute(NachatSbrosPodgotovkiUnita $command)
    {
        return yield Workflow::executeActivity(
            'NachatSbrosPodgotovkiUnitaActivity',
            [$command],
            ActivityOptions::new()
                ->withStartToCloseTimeout(60*60)
                ->withTaskQueue(WorkflowClientFactory::runnerQueueName)
        );
    }
}
