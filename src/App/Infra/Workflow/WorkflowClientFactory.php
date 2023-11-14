<?php

namespace App\App\Infra\Workflow;

use Temporal\Client\WorkflowClient;
use Temporal\Client\GRPC\ServiceClient;

final class WorkflowClientFactory
{
    const runnerQueueName = 'unitman-runner-queue';
    public function __invoke($temporalCliAddress): WorkflowClient
    {
        return WorkflowClient::create(
            ServiceClient::create(
                $temporalCliAddress
            ),
        );
    }

}
