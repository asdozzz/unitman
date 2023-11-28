<?php

namespace App\Runner\Api;

use App\Runner\Business\Command\InitProjectCommand;
use App\Runner\Business\Command\NachatObnovlenieUnita;
use App\Runner\Business\Command\NachatOstanovkuUnita;
use App\Runner\Business\Command\NachatPodgotovkuUnita;
use App\Runner\Business\Command\NachatSborkuUnita;
use App\Runner\Business\Command\NachatSbrosPodgotovkiUnita;
use App\Runner\Business\Command\NachatUdalenieUnita;
use App\Runner\Business\Command\NachatZapuskUnita;
use App\Runner\Business\Command\RemoveProjectCommand;
use App\Runner\Business\Model\InitProjectResult;
use App\Runner\Business\Model\RemoveProjectResult;
use App\Runner\Business\Model\ResultatObnovleniyaUnita;
use App\Runner\Business\Model\ResultatOstanovkiUnita;
use App\Runner\Business\Model\ResultatPodgotovkiUnita;
use App\Runner\Business\Model\ResultatSbrokiUnita;
use App\Runner\Business\Model\ResultatSbrosaPodgotovkiUnita;
use App\Runner\Business\Model\ResultatUdaleniyaUnita;
use App\Runner\Business\Model\ResultatZapuskaUnita;
use App\Runner\Infra\Workflow\InitProjectWorkflow;
use App\Runner\Infra\Workflow\NachatObnovlenieUnitaWorkflow;
use App\Runner\Infra\Workflow\NachatOstanvkuUnitaWorkflow;
use App\Runner\Infra\Workflow\NachatPodgotovkuUnitaWorkflow;
use App\Runner\Infra\Workflow\NachatSborkuUnitaWorkflow;
use App\Runner\Infra\Workflow\NachatSbrosPodgotovkiUnitaWorkflow;
use App\Runner\Infra\Workflow\NachatUdalenieUnitaWorkflow;
use App\Runner\Infra\Workflow\NachatZapuskUnitaWorkflow;
use App\Runner\Infra\Workflow\RemoveProjectWorkflow;
use Carbon\CarbonInterval;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowOptions;
use Temporal\Common\RetryOptions;
use Temporal\Exception\Client\ServiceClientException;
use Temporal\Exception\Client\WorkflowFailedException;
use Temporal\Exception\Client\WorkflowServiceException;
use Temporal\Exception\WorkflowExecutionFailedException;

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
        $workflow = $this->getWorkflowById($workflowId);
        return $this->makeResultWithConfig($workflow, ResultatSbrokiUnita::class);
    }

    public function nachatUdalenieUnita(NachatUdalenieUnita $command): string
    {
        $workflow = $this->workflowClient->newWorkflowStub(
            NachatUdalenieUnitaWorkflow::class,
            WorkflowOptions::new()
                ->withWorkflowExecutionTimeout(CarbonInterval::minute(10))
        );

        $run = $this->workflowClient->start($workflow, $command);
        return $run->getExecution()->getID();
    }

    public function poluchitResultatUdaleniyaUnita(string $workflowId): ?ResultatUdaleniyaUnita
    {
        $workflow = $this->getWorkflowById($workflowId);
        return $this->makeResult($workflow, ResultatUdaleniyaUnita::class);
    }

    public function nachatPodgotovkuUnita(NachatPodgotovkuUnita $command): string
    {
        $workflow = $this->workflowClient->newWorkflowStub(
            NachatPodgotovkuUnitaWorkflow::class,
            WorkflowOptions::new()
                ->withWorkflowExecutionTimeout(CarbonInterval::minute(10))
        );

        $run = $this->workflowClient->start($workflow, $command);
        return $run->getExecution()->getID();
    }

    public function nachatObnovlenieUnita(NachatObnovlenieUnita $command): string
    {
        $workflow = $this->workflowClient->newWorkflowStub(
            NachatObnovlenieUnitaWorkflow::class,
            WorkflowOptions::new()
                ->withWorkflowExecutionTimeout(CarbonInterval::minute(10))
        );

        $run = $this->workflowClient->start($workflow, $command);
        return $run->getExecution()->getID();
    }

    public function nachatSbrosPodgotovkiUnita(NachatSbrosPodgotovkiUnita $command): string
    {
        $workflow = $this->workflowClient->newWorkflowStub(
            NachatSbrosPodgotovkiUnitaWorkflow::class,
            WorkflowOptions::new()
                ->withWorkflowExecutionTimeout(CarbonInterval::minute(10))
        );

        $run = $this->workflowClient->start($workflow, $command);
        return $run->getExecution()->getID();
    }

    public function nachatZapuskUnita(NachatZapuskUnita $command): string
    {
        $workflow = $this->workflowClient->newWorkflowStub(
            NachatZapuskUnitaWorkflow::class,
            WorkflowOptions::new()
                ->withWorkflowExecutionTimeout(CarbonInterval::minute(10))
        );

        $run = $this->workflowClient->start($workflow, $command);
        return $run->getExecution()->getID();
    }

    public function nachatOstanovkuUnita(NachatOstanovkuUnita $command): string
    {
        $workflow = $this->workflowClient->newWorkflowStub(
            NachatOstanvkuUnitaWorkflow::class,
            WorkflowOptions::new()
                ->withWorkflowExecutionTimeout(CarbonInterval::minute(10))
        );

        $run = $this->workflowClient->start($workflow, $command);
        return $run->getExecution()->getID();
    }

    public function poluchitResultatPodgotovki(string $workflowId): ?ResultatPodgotovkiUnita
    {
        $workflow = $this->getWorkflowById($workflowId);
        return $this->makeResult($workflow, ResultatPodgotovkiUnita::class);
    }

    public function poluchitResultatObnovleniyaUnita(string $workflowId): ?ResultatObnovleniyaUnita
    {
        $workflow = $this->getWorkflowById($workflowId);
        return $this->makeResultWithConfig($workflow, ResultatObnovleniyaUnita::class);
    }

    public function poluchitResultatSbrosaPodgotovkiUnita(string $workflowId): ?ResultatSbrosaPodgotovkiUnita
    {
        $workflow = $this->getWorkflowById($workflowId);
        return $this->makeResult($workflow, ResultatSbrosaPodgotovkiUnita::class);
    }

    public function poluchitResultatZapuskaUnita(string $workflowId): ?ResultatZapuskaUnita
    {
        $workflow = $this->getWorkflowById($workflowId);
        return $this->makeResult($workflow, ResultatZapuskaUnita::class);
    }

    public function poluchitResultatOstanovkiUnita(string $workflowId): ?ResultatOstanovkiUnita
    {
        $workflow = $this->getWorkflowById($workflowId);
        return $this->makeResult($workflow, ResultatOstanovkiUnita::class);
    }

    /**
     * @param string $workflowId
     * @return \Temporal\Client\WorkflowStubInterface
     * @throws \Exception
     */
    private function getWorkflowById(string $workflowId): \Temporal\Client\WorkflowStubInterface
    {
        $workflow = $this->workflowClient->newUntypedRunningWorkflowStub($workflowId);

        if (!$workflow->hasExecution()) {
            throw new \Exception('runner.workflow_unit_not_found');
        }
        return $workflow;
    }

    /**
     * @template T
     * @param \Temporal\Client\WorkflowStubInterface $workflow
     * @param T $type
     * @return T
     * @throws \Throwable
     */
    private function makeResult(\Temporal\Client\WorkflowStubInterface $workflow, string $type): object
    {
        try {
            $result = $workflow->getResult($type, 5);
            return $result;
        } catch (WorkflowServiceException $e) {
            return new $type(false, $e->getPrevious()->getMessage());
        } catch (WorkflowExecutionFailedException|WorkflowFailedException $exp) {
            return new $type(false, $exp->getMessage());
        } catch (\Throwable $throwable) {
            throw $throwable;
        }
    }

    /**
     * @template T
     * @param \Temporal\Client\WorkflowStubInterface $workflow
     * @param T $type
     * @return T
     * @throws \Throwable
     */
    private function makeResultWithConfig(\Temporal\Client\WorkflowStubInterface $workflow, string $type): object
    {
        try {
            $result = $workflow->getResult($type, 5);
            return $result;
        } catch (WorkflowServiceException $e) {
            return new $type(false, $e->getPrevious()->getMessage(), '');
        } catch (WorkflowExecutionFailedException|WorkflowFailedException $exp) {
            return new $type(false, $exp->getMessage(), '');
        } catch (\Throwable $throwable) {
            throw $throwable;
        }
    }

}
