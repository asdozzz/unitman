<?php

declare(strict_types=1);

return [
    \App\Runner\Infra\Workflow\InitProjectWorkflow::class,
    \App\Runner\Infra\Workflow\RemoveProjectWorkflow::class,
    \App\Runner\Infra\Workflow\NachatSborkuUnitaWorkflow::class,
    \App\Runner\Infra\Workflow\NachatUdalenieUnitaWorkflow::class,
    \App\Runner\Infra\Workflow\NachatObnovlenieUnitaWorkflow::class,
    \App\Runner\Infra\Workflow\NachatPodgotovkuUnitaWorkflow::class,
    \App\Runner\Infra\Workflow\NachatSbrosPodgotovkiWorkflow::class,
    \App\Runner\Infra\Workflow\NachatZapuskUnitaWorkflow::class,
    \App\Runner\Infra\Workflow\NachatOstanvkuUnitaWorkflow::class,
    \App\Runner\Infra\Workflow\RunnerHealthCheckWorkflow::class,
];
