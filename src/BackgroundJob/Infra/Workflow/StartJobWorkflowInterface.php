<?php

namespace App\BackgroundJob\Infra\Workflow;

use Temporal\Workflow\QueryMethod;
use Temporal\Workflow\ReturnType;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
interface StartJobWorkflowInterface
{
    /**
     * @psalm-suppress MissingReturnType
     * */
    #[WorkflowMethod(name: "startJob")]
    #[ReturnType("int")]
    function startJob();

    #[SignalMethod]
    function pause(): void;

    #[SignalMethod]
    function unpause(): void;

    #[SignalMethod]
    function stop(): void;

    #[QueryMethod]
    function getCounter(): array;
}
