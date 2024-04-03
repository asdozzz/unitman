<?php

namespace App\Unitman\Infra\Temporal\Workflow;

use App\BackgroundJob\Infra\Service\BackgroundJobInterface;

final class OcheredUnitovWorkflowJob implements BackgroundJobInterface
{
    function getWorkflowClass(): string
    {
        return OcheredUnitovWorkflow::class;
    }
    function getMethodName(): string
    {
        return 'run';
    }

    function getName(): string
    {
        return 'ochered_unitov';
    }
}
