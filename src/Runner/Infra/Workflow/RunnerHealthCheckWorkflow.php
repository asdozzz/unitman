<?php

namespace App\Runner\Infra\Workflow;
use App\App\Infra\Workflow\WorkflowClientFactory;
use App\Runner\Business\Model\GolangRunner\Runner\RunnerHealthCheckResult;
use App\Runner\Infra\Activity\UstanovitResultatRabotosposobnostiRunneraActivity;
use Carbon\CarbonInterval;
use Temporal\Activity\ActivityOptions;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
final class RunnerHealthCheckWorkflow
{
    /**
     * @var UstanovitResultatRabotosposobnostiRunneraActivity
     * @psalm-suppress MissingPropertyType
     * */
    private $activity;

    public function __construct()
    {
        $this->activity = Workflow::newActivityStub(
            UstanovitResultatRabotosposobnostiRunneraActivity::class,
            ActivityOptions::new()
                ->withScheduleToCloseTimeout(CarbonInterval::seconds(10))
                ->withTaskQueue(WorkflowClientFactory::monoQueueName)
        );
    }

    /**
     * @psalm-suppress MissingReturnType
     * */
    #[WorkflowMethod(name: "RunnerHealthCheck")]
    public function run(): \Generator
    {
        $runners = yield $this->activity->getAll();

        foreach ($runners as $runner) {
            $result = yield Workflow::executeActivity(
                'RunnerHealthCheckActivity',
                [],
                ActivityOptions::new()
                    ->withStartToCloseTimeout(5)
                    ->withTaskQueue($runner['taskQueue'])
            );
            /** @var RunnerHealthCheckResult $result*/
            yield $this->activity->updateState($runner['id'], $result->Success);
        }

        return "<pre>" . print_r($runners, true) . "</pre>";
    }
}
