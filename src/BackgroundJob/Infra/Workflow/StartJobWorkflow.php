<?php

namespace App\BackgroundJob\Infra\Workflow;

use Temporal\Client\WorkflowOptions;
use Temporal\Workflow;
use Temporal\Workflow\ContinueAsNewOptions;
use Temporal\Workflow\QueryMethod;
use Temporal\Workflow\ReturnType;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
final class StartJobWorkflow
{
    private bool $pause = false;
    private bool $stop = false;
    private int $counter = 0;

    /**
     * @psalm-suppress MissingReturnType
     * @psalm-param class-string $className
     * */
    #[WorkflowMethod(name: "startJob")]
    #[ReturnType("int")]
    function startJob(string $className, string $method)
    {
        while (true) {
            yield Workflow::timer(1);

            if ($this->pause) {
                continue;
            }

            if ($this->stop) {
                break;
            }

            try {
                $child = Workflow::newChildWorkflowStub(
                    $className,
                    Workflow\ChildWorkflowOptions::new()
                        ->withTaskQueue(\App\App\Infra\Workflow\WorkflowClientFactory::monoQueueName)

                );
                yield $child->{$method}();
            } catch (\Exception $e) {
            }

            $this->counter++;
        }

        return 'OK';
    }

    #[SignalMethod]
    function pause(): void
    {
        $this->pause = true;
    }

    #[SignalMethod]
    function unpause(): void
    {
        $this->pause = false;
    }

    #[SignalMethod]
    function stop(): void
    {
        $this->stop = true;
    }

    #[QueryMethod]
    function getCounter(): array
    {
        return [
            'counter' => $this->counter,
            'pause' => $this->pause,
            'stop' => $this->stop
        ];
    }
}
