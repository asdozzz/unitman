<?php

namespace App\App\Infra\Workflow;

use Temporal\Client\WorkflowClient;
use Temporal\Client\GRPC\ServiceClient;

final class WorkflowClientFactory
{
    const runnerQueueName = 'unitman-runner-queue';
    const monoQueueName = "unitman-mono-queue";
    public function __invoke(string $temporalCliAddress): WorkflowClient
    {
        $client = WorkflowClient::create(
            ServiceClient::create(
                $temporalCliAddress
            ),
        );

        return $client;
    }

}
