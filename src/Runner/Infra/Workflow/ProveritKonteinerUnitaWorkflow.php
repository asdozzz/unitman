<?php

namespace App\Runner\Infra\Workflow;
use App\App\Infra\Workflow\WorkflowClientFactory;
use App\Runner\Business\Command\InitProjectCommand;
use App\Runner\Business\Command\ProveritKonteinerUnita;
use App\Runner\Business\Model\GolangRunner\Project\InitProjectResult;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatProverkiKonteineraUnita;
use Temporal\Activity\ActivityOptions;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
final class ProveritKonteinerUnitaWorkflow
{
    /**
     * @psalm-suppress MissingReturnType
     * */
    #[WorkflowMethod(name: "ProveritKonteinerUnita")]
    #[Workflow\ReturnType(ResultatProverkiKonteineraUnita::class)]
    public function proverit(ProveritKonteinerUnita $command)
    {
        return yield Workflow::executeActivity(
            'ProveritKonteinerUnitaActivity',
            [$command],
            ActivityOptions::new()
                ->withStartToCloseTimeout(30)
                ->withTaskQueue(WorkflowClientFactory::runnerQueueName)
        );
    }
}
