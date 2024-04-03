<?php

namespace App\Unitman\Infra\Temporal\Workflow;

use App\App\Infra\Workflow\WorkflowClientFactory;
use App\BackgroundJob\Infra\Service\BackgroundJobInterface;
use App\Unitman\Infra\Temporal\Activity\OcheredUnitovActivity;
use Carbon\CarbonInterval;
use Temporal\Activity\ActivityOptions;
use Temporal\Promise;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
final class OcheredUnitovWorkflow
{
    private $ocheredUnitovActivity;

    public function __construct()
    {
        $this->ocheredUnitovActivity = Workflow::newActivityStub(
            OcheredUnitovActivity::class,
            ActivityOptions::new()
                ->withScheduleToCloseTimeout(CarbonInterval::seconds(10))
                ->withTaskQueue(WorkflowClientFactory::monoQueueName)
        );
    }

    #[WorkflowMethod('ObrabotkaZadach')]
    function run()
    {
        $zadachi = yield $this->ocheredUnitovActivity->poluchitZadachiNaObrabotku(20);

        $promises = [];
        foreach ($zadachi as $zadacha) {
            $promises[] = $this->ocheredUnitovActivity->obrabotatZadachu($zadacha);
        }

        yield Promise::all($promises);

        return 'OK';
    }


}
