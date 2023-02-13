<?php

declare(strict_types=1);

namespace App\App;

use App\App\Infra\Workflow\ActivityCollection;
use App\App\Infra\Workflow\AppActivityInterface;
use App\App\Infra\Workflow\AppWorkflowInterface;
use App\App\Infra\Workflow\GreetingActivity;
use App\App\Infra\Workflow\GreetingWorkflow;
use App\App\Infra\Workflow\WorkflowCollection;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return function (ContainerConfigurator $configuration) {
    $services = $configuration->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('App\\App\\', './{Business,Infra,Acl}')
        ->exclude(['./{di.php, Tests}','./Business/Command','./Business/Model'])
        ->public();

   /* $services->instanceof(AppWorkflowInterface::class)
        ->tag('app.workflow');*/

   /* $services->instanceof(AppActivityInterface::class)
        ->tag('app.activity');*/

    $services->set(GreetingWorkflow::class)
        ->tag('app.workflow');

    $services->set(GreetingActivity::class)
        ->tag('app.activity');

    $services->set(ActivityCollection::class)
        ->args([tagged_iterator('app.activity')])->public();

    $services->set(WorkflowCollection::class)
        ->args([tagged_iterator('app.workflow')]);
};
