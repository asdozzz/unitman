<?php

namespace App\BackgroundJob\Infra\Workflow;

use Temporal\Workflow;
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

    #[WorkflowMethod(name: "startJob")]
    #[ReturnType("int")]
    function startJob(string $className, string $method, array $args)
    {
        $result = 0;
        while (true) {
            yield Workflow::timer(2);

            if ($this->pause) {
                continue;
            }

            if ($this->stop) {
                break;
            }

            $child = Workflow::newChildWorkflowStub($className);
            $init = yield $child->{$method}(...$args);
            $result += $init;

            $this->counter++;

            if ($this->counter >= 3) {
                return Workflow::newContinueAsNewStub(self::class)->startJob($className, $method, $args);
            }

        }

        return $result;
    }

    #[SignalMethod]
    function pause()
    {
        $this->pause = true;
    }

    #[SignalMethod]
    function unpause()
    {
        $this->pause = false;
    }

    #[SignalMethod]
    function stop()
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
