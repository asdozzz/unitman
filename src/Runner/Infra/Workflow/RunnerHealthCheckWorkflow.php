<?php

namespace App\Runner\Infra\Workflow;
use App\App\Infra\Workflow\WorkflowClientFactory;
use App\Runner\Business\Model\GolangRunner\Runner\RunnerHealthCheckResult;
use App\Runner\Infra\Activity\UstanovitResultatRabotosposobnostiRunneraActivity;
use Carbon\CarbonInterval;
use PharIo\Version\Exception;
use Temporal\Activity\ActivityOptions;
use Temporal\Common\RetryOptions;
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
                ->withScheduleToCloseTimeout(CarbonInterval::seconds(30))
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
        $results = [];
        foreach ($runners as $runner) {
            try {
                $result = yield Workflow::executeActivity(
                    'RunnerHealthCheckActivity',
                    [],
                    ActivityOptions::new()
                        ->withRetryOptions(
                            RetryOptions::new()
                                ->withMaximumAttempts(1)
                        )
                        ->withStartToCloseTimeout(20)
                        ->withScheduleToStartTimeout(20)
                        ->withTaskQueue($runner['taskQueue'])
                );
                /** @var RunnerHealthCheckResult $result*/
                yield $this->activity->setSuccessState($runner['id'], $result);
                yield $this->activity->obnovitStatistikuPoUnitam($runner['id']);
                $results[] = $result;
            } catch (\Throwable $e) {
                yield $this->activity->setErrorState($runner['id']);
            }

        }

        return $results;
    }
}
