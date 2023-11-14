<?php

namespace App\Runner\Api;

use App\Runner\Business\Command\InitProjectCommand;
use App\Runner\Business\Command\NachatSborkuUnita;
use App\Runner\Business\Command\RemoveProjectCommand;
use App\Runner\Business\Model\InitProjectResult;
use App\Runner\Business\Model\RemoveProjectResult;
use App\Runner\Business\Model\ResultatSbrokiUnita;
use App\Runner\Infra\Workflow\InitProjectWorkflow;
use App\Runner\Infra\Workflow\NachatSborkuUnitaWorkflow;
use App\Runner\Infra\Workflow\RemoveProjectWorkflow;
use Carbon\CarbonInterval;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowOptions;
use Temporal\Common\RetryOptions;
use Temporal\Exception\Client\ServiceClientException;
use Temporal\Exception\Client\WorkflowFailedException;
use Temporal\Exception\Client\WorkflowServiceException;
use Temporal\Exception\WorkflowExecutionFailedException;
use Twig\Error\Error;

final class RunnerApi
{
    public function __construct(private WorkflowClient $workflowClient)
    {
    }

    public function initProject(InitProjectCommand $command): InitProjectResult
    {
        $workflow = $this->workflowClient->newWorkflowStub(
            InitProjectWorkflow::class,
            WorkflowOptions::new()
                ->withWorkflowExecutionTimeout(CarbonInterval::minute())
        );
        $result = $workflow->initProject($command);

        return $result;
    }

    public function removeProject(RemoveProjectCommand $command): RemoveProjectResult
    {
        $workflow = $this->workflowClient->newWorkflowStub(
            RemoveProjectWorkflow::class,
            WorkflowOptions::new()
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(10))
        );
        $result = $workflow->removeProject($command);

        return $result;
    }

    public function nachatSborkuUnita(NachatSborkuUnita $command): string
    {
        $workflow = $this->workflowClient->newWorkflowStub(
            NachatSborkuUnitaWorkflow::class,
            WorkflowOptions::new()
                ->withWorkflowExecutionTimeout(CarbonInterval::minute(10))
        );

        $run = $this->workflowClient->start($workflow, $command);
        return $run->getExecution()->getID();
    }

    public function poluchitResultatSborki(string $workflowId): ?ResultatSbrokiUnita
    {
        $workflow = $this->workflowClient->newUntypedRunningWorkflowStub($workflowId);

        if (!$workflow->hasExecution()) {
            throw new \Exception('runner.workflow_unit_not_found');
        }
        try {
            $result = $workflow->getResult(ResultatSbrokiUnita::class, 5);
            return $result;
        } catch (WorkflowServiceException $e) {
            return new ResultatSbrokiUnita(false, $e->getPrevious()->getMessage(), '');
        } catch (WorkflowExecutionFailedException|WorkflowFailedException $exp) {
            return new ResultatSbrokiUnita(false, $exp->getMessage(), '');
        } catch (\Throwable $throwable) {
            throw $throwable;
        }
    }
}
