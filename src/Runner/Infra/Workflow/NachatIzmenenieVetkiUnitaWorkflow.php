<?php

namespace App\Runner\Infra\Workflow;

use App\App\Infra\Workflow\WorkflowClientFactory;
use App\Runner\Business\Command\NachatIzmenenieVetkiUnita;
use App\Runner\Business\Command\NachatObnovlenieUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatIzmeneniyaVetkiUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatObnovleniyaUnita;
use Temporal\Activity\ActivityOptions;
use Temporal\Workflow;
use Temporal\Workflow\ReturnType;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
final class NachatIzmenenieVetkiUnitaWorkflow
{
    /**
     * @psalm-suppress MissingReturnType
     * */
    #[WorkflowMethod]
    #[ReturnType(ResultatIzmeneniyaVetkiUnita::class)]
    public function execute(NachatIzmenenieVetkiUnita $command)
    {
        return yield Workflow::executeActivity(
            'NachatIzmeneniyaVetkiUnitaActivity',
            [$command],
            ActivityOptions::new()
                ->withStartToCloseTimeout(60*60)
                ->withTaskQueue(WorkflowClientFactory::runnerQueueName)
        );
    }
}
