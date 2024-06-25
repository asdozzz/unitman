<?php

namespace App\Runner\Api;

use App\Runner\Business\Command\InitProjectCommand;
use App\Runner\Business\Command\NachatIzmenenieVetkiUnita;
use App\Runner\Business\Command\NachatObnovlenieUnita;
use App\Runner\Business\Command\NachatOstanovkuUnita;
use App\Runner\Business\Command\NachatPodgotovkuUnita;
use App\Runner\Business\Command\NachatSborkuUnita;
use App\Runner\Business\Command\NachatSbrosPodgotovkiUnita;
use App\Runner\Business\Command\NachatUdalenieUnita;
use App\Runner\Business\Command\NachatZapuskUnita;
use App\Runner\Business\Command\RemoveProjectCommand;
use App\Runner\Business\Model\GolangRunner\Project\InitProjectResult;
use App\Runner\Business\Model\GolangRunner\Project\RemoveProjectResult;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatIzmeneniyaVetkiUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatObnovleniyaUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatOstanovkiUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatPodgotovkiUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatSbrokiUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatSbrosaPodgotovkiUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatUdaleniyaUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatZapuskaUnita;
use App\Runner\Business\Port\RunnerRepository;
use App\Runner\Infra\Workflow\InitProjectWorkflow;
use App\Runner\Infra\Workflow\NachatIzmenenieVetkiUnitaWorkflow;
use App\Runner\Infra\Workflow\NachatObnovlenieUnitaWorkflow;
use App\Runner\Infra\Workflow\NachatOstanvkuUnitaWorkflow;
use App\Runner\Infra\Workflow\NachatPodgotovkuUnitaWorkflow;
use App\Runner\Infra\Workflow\NachatSborkuUnitaWorkflow;
use App\Runner\Infra\Workflow\NachatSbrosPodgotovkiWorkflow;
use App\Runner\Infra\Workflow\NachatUdalenieUnitaWorkflow;
use App\Runner\Infra\Workflow\NachatZapuskUnitaWorkflow;
use App\Runner\Infra\Workflow\RemoveProjectWorkflow;
use Carbon\CarbonInterval;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowOptions;
use Temporal\Exception\Client\WorkflowFailedException;
use Temporal\Exception\Client\WorkflowServiceException;
use Temporal\Exception\WorkflowExecutionFailedException;

final class RunnerApi
{
    public function __construct(private WorkflowClient $workflowClient, private RunnerRepository $runnerRepository)
    {
    }

    function getTaskQueue(): string
    {
        $runnerState = $this->runnerRepository->getDefaultRunnerState();

        if (!$runnerState->isActive()) {
            throw new \Exception('runner.state.is_non_active');
        }

        return $runnerState->getTaskQueue();
    }

    public function initProject(InitProjectCommand $command): InitProjectResult
    {
        $workflow = $this->workflowClient->newWorkflowStub(
            InitProjectWorkflow::class,
            WorkflowOptions::new()
                ->withTaskQueue(\App\App\Infra\Workflow\WorkflowClientFactory::monoQueueName)
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
                ->withTaskQueue(\App\App\Infra\Workflow\WorkflowClientFactory::monoQueueName)
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
                ->withTaskQueue(\App\App\Infra\Workflow\WorkflowClientFactory::monoQueueName)
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
                ->withTaskQueue(\App\App\Infra\Workflow\WorkflowClientFactory::monoQueueName)
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

    public function poluchitResultatIzmeneniyaVetki(string $workflowId): ?ResultatIzmeneniyaVetkiUnita
    {
        $workflow = $this->getWorkflowById($workflowId);
        return $this->makeResultWithConfig($workflow, ResultatIzmeneniyaVetkiUnita::class);
    }

    public function nachatPodgotovkuUnita(NachatPodgotovkuUnita $command): string
    {
        $workflow = $this->workflowClient->newWorkflowStub(
            NachatPodgotovkuUnitaWorkflow::class,
            WorkflowOptions::new()
                ->withTaskQueue(\App\App\Infra\Workflow\WorkflowClientFactory::monoQueueName)
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
                ->withTaskQueue(\App\App\Infra\Workflow\WorkflowClientFactory::monoQueueName)
                ->withWorkflowExecutionTimeout(CarbonInterval::minute(10))
        );

        $run = $this->workflowClient->start($workflow, $command);
        return $run->getExecution()->getID();
    }

    public function nachatIzmenenieVetkiUnita(NachatIzmenenieVetkiUnita $command): string
    {
        $workflow = $this->workflowClient->newWorkflowStub(
            NachatIzmenenieVetkiUnitaWorkflow::class,
            WorkflowOptions::new()
                ->withTaskQueue(\App\App\Infra\Workflow\WorkflowClientFactory::monoQueueName)
                ->withWorkflowExecutionTimeout(CarbonInterval::minute(10))
        );

        $run = $this->workflowClient->start($workflow, $command);
        return $run->getExecution()->getID();
    }

    public function nachatSbrosPodgotovkiUnita(NachatSbrosPodgotovkiUnita $command): string
    {
        $workflow = $this->workflowClient->newWorkflowStub(
            NachatSbrosPodgotovkiWorkflow::class,
            WorkflowOptions::new()
                ->withTaskQueue(\App\App\Infra\Workflow\WorkflowClientFactory::monoQueueName)
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
                ->withTaskQueue(\App\App\Infra\Workflow\WorkflowClientFactory::monoQueueName)
                ->withWorkflowExecutionTimeout(CarbonInterval::minute(20))
        );

        $run = $this->workflowClient->start($workflow, $command);
        return $run->getExecution()->getID();
    }

    public function nachatOstanovkuUnita(NachatOstanovkuUnita $command): string
    {
        $workflow = $this->workflowClient->newWorkflowStub(
            NachatOstanvkuUnitaWorkflow::class,
            WorkflowOptions::new()
                ->withTaskQueue(\App\App\Infra\Workflow\WorkflowClientFactory::monoQueueName)
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
     * @param class-string<T> $type
     * @return T|null
     * @throws \Throwable
     */
    private function makeResult(\Temporal\Client\WorkflowStubInterface $workflow, string $type): ?object
    {
        try {
            $result = $workflow->getResult($type, 5);
            return $result;
        } catch (WorkflowServiceException $e) {
            $msg = $e->getPrevious()?->getMessage() ?? 'runner.unknown_error_when_make_result';
            $steps = [$this->stepAsArray('error workflow', $msg, false, time())];
            return new $type(0, $steps);
        } catch (WorkflowExecutionFailedException|WorkflowFailedException $exp) {
            $steps = [$this->stepAsArray('error workflow', $exp->getMessage(), false, time())];
            return new $type(0, $steps);
        } catch (\Throwable $throwable) {
            throw $throwable;
        }
    }

    /**
     * @template T
     * @param \Temporal\Client\WorkflowStubInterface $workflow
     * @param class-string<T> $type
     * @return T|null
     * @throws \Throwable
     */
    private function makeResultWithConfig(\Temporal\Client\WorkflowStubInterface $workflow, string $type): ?object
    {
        try {
            $result = $workflow->getResult($type, 5);
            return $result;
        } catch (WorkflowServiceException $e) {
            $msg = $e->getPrevious()?->getMessage() ?? 'runner.unknown_error_when_make_result_with_config';
            $steps = [$this->stepAsArray('error workflow', $msg, false, time())];
            return new $type(0, $steps);
        } catch (WorkflowExecutionFailedException|WorkflowFailedException $exp) {
            $msg = $exp->getMessage() ?? 'runner.unknown_error_when_make_result_with_config';
            $steps = [$this->stepAsArray('error workflow', $msg, false, time())];
            return new $type(0, $steps);
        } catch (\Throwable $throwable) {
            throw $throwable;
        }
    }

    private function stepAsArray(string $command, string $response, bool $success, int $unixtime): array
    {
        return ['Command' => $command, 'Response' => $response, 'Success'=>$success, 'Unixtime' => $unixtime];
    }
}
