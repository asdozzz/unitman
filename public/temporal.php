<?php

use App\Kernel;

ini_set('display_errors', 'stderr');
require_once __DIR__."/../vendor/autoload_runtime.php";

use Temporal\WorkerFactory;

// factory initiates and runs task queue specific activity and workflow workers
$factory = WorkerFactory::create();

// Worker that listens on a task queue and hosts both workflow and activity implementations.
$worker = $factory->newWorker();

$worker->registerWorkflowTypes(\App\App\Infra\Workflow\GreetingWorkflow::class);
$worker->registerActivity(\App\App\Infra\Workflow\GreetingActivity::class);

// We can use task queue for more complex task routing, for example our FileProcessing
// activity will receive unique, host specific, TaskQueue which can be used to process
// files locally.
$hostTaskQueue = gethostname();

$factory->newWorker(
    $hostTaskQueue,
    \Temporal\Worker\WorkerOptions::new()->withMaxConcurrentActivityExecutionSize(10)
);

// start primary loop
$factory->run();
