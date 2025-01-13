<?php

declare(strict_types=1);

return [
    \App\Runner\Infra\Workflow\NachatOchistkuProektaWorkflow::class,
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
    \App\Runner\Infra\Workflow\NachatIzmenenieVetkiUnitaWorkflow::class,
    App\Unitman\Infra\Temporal\Workflow\ProzesObnovlenieKodaPosleZapuskaWorkflow::class,
    \App\Unitman\Infra\Temporal\Workflow\ProzesUdaleniyaUnitaPosleZapuskaWorkflow::class,
    \App\Unitman\Infra\Temporal\Workflow\ProzesAvtosborkiUnitaSystemoiWorkflow::class,
    \App\Runner\Infra\Workflow\NachatDeistvieUnitaWorkflow::class
];
