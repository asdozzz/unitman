<?php

namespace App\Runner\Infra\Workflow;
use App\Runner\Business\Command\ProveritKonteinerUnita;
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
    public function proverit(ProveritKonteinerUnita $command, string $taskQueue = '')
    {
        if ($taskQueue === '') {
            throw new \InvalidArgumentException('taskQueue обязателен для запуска workflow');
        }

        return yield Workflow::executeActivity(
            'ProveritKonteinerUnitaActivity',
            [$command],
            ActivityOptions::new()
                ->withStartToCloseTimeout(30)
                ->withTaskQueue($taskQueue)
        );
    }
}
