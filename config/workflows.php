<?php

declare(strict_types=1);

return [
    \App\BackgroundJob\Infra\Workflow\StartJobWorkflow::class,
    \App\BackgroundJob\Infra\Workflow\ChildWorkflow::class,
    \App\Runner\Infra\Workflow\InitProjectWorkflow::class,
    \App\Runner\Infra\Workflow\RemoveProjectWorkflow::class,
    \App\Runner\Infra\Workflow\NachatSborkuUnitaWorkflow::class,
    \App\Runner\Infra\Workflow\NachatUdalenieUnitaWorkflow::class,
    \App\Runner\Infra\Workflow\NachatObnovlenieUnitaWorkflow::class,
    \App\Runner\Infra\Workflow\NachatPodgotovkuUnitaWorkflow::class,
    \App\Runner\Infra\Workflow\NachatSbrosPodgotovkiUnitaWorkflow::class,
    \App\Runner\Infra\Workflow\NachatZapuskUnitaWorkflow::class,
    \App\Runner\Infra\Workflow\NachatOstanvkuUnitaWorkflow::class,
    \App\Unitman\Infra\Temporal\Workflow\OcheredUnitovWorkflow::class
];
