<?php

ini_set('display_errors', 'stderr');
require_once __DIR__."/../vendor/autoload.php";

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/*$container = new ContainerBuilder();
$loader = new PhpFileLoader($container, new FileLocator(__DIR__));
$loader->load(__DIR__.'/../src/Account/di.php');
$loader->load(__DIR__.'/../src/App/di.php');
$loader->load(__DIR__.'/../src/BackgroundJob/di.php');
$loader->load(__DIR__.'/../src/Runner/di.php');
$loader->load(__DIR__.'/../src/Unitman/di.php');
$loader->load(__DIR__.'/../src/Utils/di.php');
$container->compile();*/

/*$kernel = new \App\Kernel('prod', false);
$kernel->boot();
$container = $kernel->getContainer();*/
use Temporal\WorkerFactory;

// factory initiates and runs task queue specific activity and workflow workers
$factory = WorkerFactory::create();

// Worker that listens on a task queue and hosts both workflow and activity implementations.
$worker = $factory->newWorker(\App\App\Infra\Workflow\WorkflowClientFactory::monoQueueName);

$worker->registerWorkflowTypes(\App\BackgroundJob\Infra\Workflow\StartJobWorkflow::class);
$worker->registerWorkflowTypes(\App\BackgroundJob\Infra\Workflow\ChildWorkflow::class);

$worker->registerWorkflowTypes(\App\Runner\Infra\Workflow\InitProjectWorkflow::class);
$worker->registerWorkflowTypes(\App\Runner\Infra\Workflow\RemoveProjectWorkflow::class);
$worker->registerWorkflowTypes(\App\Runner\Infra\Workflow\NachatSborkuUnitaWorkflow::class);
$worker->registerWorkflowTypes(\App\Runner\Infra\Workflow\NachatUdalenieUnitaWorkflow::class);
$worker->registerWorkflowTypes(\App\Runner\Infra\Workflow\NachatObnovlenieUnitaWorkflow::class);
$worker->registerWorkflowTypes(\App\Runner\Infra\Workflow\NachatPodgotovkuUnitaWorkflow::class);
$worker->registerWorkflowTypes(\App\Runner\Infra\Workflow\NachatSbrosPodgotovkiUnitaWorkflow::class);
$worker->registerWorkflowTypes(\App\Runner\Infra\Workflow\NachatZapuskUnitaWorkflow::class);
$worker->registerWorkflowTypes(\App\Runner\Infra\Workflow\NachatOstanvkuUnitaWorkflow::class);

$worker->registerActivity(\App\Runner\Infra\Activity\UstanovitResultatSbrosaPodgotovkiUnitaActivity::class);

// start primary loop
$factory->run();
