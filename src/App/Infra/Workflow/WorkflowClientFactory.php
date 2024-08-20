<?php

namespace App\App\Infra\Workflow;

use Temporal\Client\WorkflowClient;
use Temporal\Client\GRPC\ServiceClient;

final class WorkflowClientFactory
{
    const runnerQueueName = 'unitman-runner-queue';
    const monoQueueName = "unitman-mono-queue";
    /**
     * @param non-empty-string $temporalCliAddress
     * */
    public function __invoke(string $temporalCliAddress): WorkflowClient
    {
        $serviceClient = ServiceClient::create(
            $temporalCliAddress
        );
        $client = WorkflowClient::create(
            $serviceClient,
        );

        return $client;
    }

}
