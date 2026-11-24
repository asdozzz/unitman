<?php

namespace App\Runner\Api;

use App\Runner\Business\Command\IzmenitRezhimRedaktoraUnita;
use App\Runner\Business\Command\NachatDeistvieUnita;
use App\Runner\Business\Command\NachatHodOpenCode;
use App\Runner\Business\Command\VipolnitHodOpenCode;
use App\Runner\Business\Command\NachatObnovlenieUnita;
use App\Runner\Business\Command\NachatOstanovkuUnita;
use App\Runner\Business\Command\NachatPodgotovkuUnita;
use App\Runner\Business\Command\NachatSborkuUnita;
use App\Runner\Business\Command\NachatSbrosPodgotovkiUnita;
use App\Runner\Business\Command\NachatUdalenieUnita;
use App\Runner\Business\Command\NachatZapuskUnita;
use App\Runner\Business\Command\PodgotovitSessiyuRedaktora;
use App\Runner\Business\Command\ProveritKonteinerUnita;
use App\Runner\Business\Command\RemoveProjectCommand;
use App\Runner\Business\Command\Workspace\OstanovitWorkspace;
use App\Runner\Business\Command\Workspace\ProektWorkspaceCommand;
use App\Runner\Business\Command\Workspace\ProveritIzmeneniyaWorkspace;
use App\Runner\Business\Command\Workspace\ResultatOstanovkiWorkspace;
use App\Runner\Business\Command\Workspace\ResultatProverkiIzmeneniyWorkspace;
use App\Runner\Business\Command\Workspace\ResultatUdaleniyaWorkspace;
use App\Runner\Business\Command\Workspace\ResultatZapuskaWorkspace;
use App\Runner\Business\Command\Workspace\UdalitWorkspace;
use App\Runner\Business\Command\Workspace\ZapustitWorkspace;
use App\Runner\Business\Command\Workspace\ZapustitWorkspaceZapros;
use App\Runner\Business\Model\GolangRunner\OpenCode\ResultatHodaOpenCode;
use App\Runner\Business\Model\GolangRunner\Project\RemoveProjectResult;
use App\Runner\Business\Model\GolangRunner\Project\ResultatOchistkiProekta;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatDeistviyaUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatIzmeneniyaRezhimaRedaktora;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatObnovleniyaUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatOstanovkiUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatPodgotovkiSessiiRedaktora;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatPodgotovkiUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatProverkiKonteineraUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatSbrokiUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatSbrosaPodgotovkiUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatUdaleniyaUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatZapuskaUnita;
use App\Runner\Business\Model\GolangRunner\Unit\Step;
use App\Runner\Business\Model\RunnerProgress;
use App\Runner\Business\Model\RunnerState;
use App\Runner\Business\Port\UmeetChitatProgressZadachi;
use App\Runner\Business\Port\UmeetHranitProgressZadachi;
use App\Runner\Business\Port\UmeetPoluchatRekvizityOpenCode;
use App\Runner\Business\Port\UmeetPoluchatUrlKlonaProekta;
use App\Runner\Business\UseCase\RunnerSelectionService;
use App\Runner\Infra\Service\RedisService;
use App\Runner\Infra\Service\RunnerProgressPublisher;
use App\Runner\Infra\Workflow\IzmenitRezhimRedaktoraUnitaWorkflow;
use App\Runner\Infra\Workflow\NachatDeistvieUnitaWorkflow;
use App\Runner\Infra\Workflow\OpenCodeTurnWorkflow;
use App\Runner\Infra\Workflow\NachatObnovlenieUnitaWorkflow;
use App\Runner\Infra\Workflow\NachatOstanovkuUnitaWorkflow;
use App\Runner\Infra\Workflow\NachatPodgotovkuUnitaWorkflow;
use App\Runner\Infra\Workflow\NachatSborkuUnitaWorkflow;
use App\Runner\Infra\Workflow\NachatSbrosPodgotovkiWorkflow;
use App\Runner\Infra\Workflow\NachatUdalenieUnitaWorkflow;
use App\Runner\Infra\Workflow\NachatZapuskUnitaWorkflow;
use App\Runner\Infra\Workflow\PodgotovitSessiyuRedaktoraWorkflow;
use App\Runner\Infra\Workflow\ProveritKonteinerUnitaWorkflow;
use App\Runner\Infra\Workflow\ProveritIzmeneniyaWorkspaceWorkflow;
use App\Runner\Infra\Workflow\OstanovitWorkspaceWorkflow;
use App\Runner\Infra\Workflow\RemoveProjectWorkflow;
use App\Runner\Infra\Workflow\UdalitWorkspaceWorkflow;
use App\Runner\Infra\Workflow\ZapustitWorkspaceWorkflow;
use Carbon\CarbonInterval;
use Symfony\Component\Serializer\SerializerInterface;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowOptions;
use Temporal\Exception\Client\TimeoutException;
use Temporal\Exception\Client\WorkflowFailedException;
use Temporal\Exception\Client\WorkflowServiceException;
use Temporal\Exception\WorkflowExecutionFailedException;

final class RunnerApi implements RunnerApiInterface
{
    public function __construct(
        private WorkflowClient $workflowClient,
        private RunnerSelectionService $runnerSelectionService,
        private float $minFreeMemoryPercent,
        private RedisService $redisService,
        private SerializerInterface $serializer,
        private UmeetHranitProgressZadachi $progressStorage,
        private UmeetChitatProgressZadachi $progressReader,
        private UmeetPoluchatRekvizityOpenCode $rekvizityOpenCode,
        private UmeetPoluchatUrlKlonaProekta $urlKlonaProekta,
    )
    {
    }

    public function zaregistrirovatProgressToken(string $workflowId): void
    {
        $this->progressStorage->zaregistrirovat($workflowId);
    }

    public function poluchitProgressZadachi(string $workflowId): ?RunnerProgress
    {
        return $this->progressReader->poluchit($workflowId);
    }

    public function poluchitKanalProgressa(string $workflowId): string
    {
        return RunnerProgressPublisher::kanalDlyaToken($workflowId);
    }

    public function getTaskQueue(): string
    {
        return $this->runnerSelectionService->getTaskQueue();
    }

    public function removeProject(RemoveProjectCommand $command): string
    {
        $runnerState = $this->resolveRunnerState($command->TaskQueue, $command->RunnerId);
        $taskQueue = $runnerState->getTaskQueue();

        $workflow = $this->workflowClient->newWorkflowStub(
            RemoveProjectWorkflow::class,
            WorkflowOptions::new()
                ->withTaskQueue(\App\App\Infra\Workflow\WorkflowClientFactory::monoQueueName)
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(10))
        );
        $run = $this->workflowClient->start($workflow, $command, $taskQueue);
        return $run->getExecution()->getID();
    }

    public function poluchitResultatUdaleniyaProekta(string $workflowId): ?RemoveProjectResult
    {
        $workflow = $this->getWorkflowById($workflowId);
        return $this->makeResult($workflow, RemoveProjectResult::class);
    }

    public function poluchitResultatOchistkiProekta(string $workflowId): ?ResultatOchistkiProekta
    {
        $workflow = $this->getWorkflowById($workflowId);
        return $this->makeResult($workflow, ResultatOchistkiProekta::class);
    }

    public function nachatSborkuUnita(string $workflowId,NachatSborkuUnita $command): string
    {
        $runnerState = $this->resolveRunnerState($command->TaskQueue, $command->RunnerId, $command->Id);
        $taskQueue = $runnerState->getTaskQueue();

        $this->proveritResursy($runnerState);
        $this->zaregistrirovatProgressToken($workflowId);

        $workflow = $this->workflowClient->newWorkflowStub(
            NachatSborkuUnitaWorkflow::class,
            WorkflowOptions::new()
                ->withWorkflowId($workflowId)
                ->withTaskQueue(\App\App\Infra\Workflow\WorkflowClientFactory::monoQueueName)
                ->withWorkflowExecutionTimeout(CarbonInterval::minute(10))
        );

        $run = $this->workflowClient->start($workflow, $command, $taskQueue);
        return $run->getExecution()->getID();
    }

    public function poluchitResultatSborki(string $workflowId): ?ResultatSbrokiUnita
    {
        $workflow = $this->getWorkflowById($workflowId);
        return $this->makeResultWithConfig($workflow, ResultatSbrokiUnita::class);
    }

    public function nachatUdalenieUnita(string $workflowId,NachatUdalenieUnita $command): string
    {
        $runnerState = $this->resolveRunnerState($command->TaskQueue, $command->RunnerId, $command->Id);
        $taskQueue = $runnerState->getTaskQueue();

        $this->zaregistrirovatProgressToken($workflowId);

        $workflow = $this->workflowClient->newWorkflowStub(
            NachatUdalenieUnitaWorkflow::class,
            WorkflowOptions::new()
                ->withWorkflowId($workflowId)
                ->withTaskQueue(\App\App\Infra\Workflow\WorkflowClientFactory::monoQueueName)
                ->withWorkflowExecutionTimeout(CarbonInterval::minute(10))
        );

        $run = $this->workflowClient->start($workflow, $command, $taskQueue);
        return $run->getExecution()->getID();
    }

    public function poluchitResultatUdaleniyaUnita(string $workflowId): ?ResultatUdaleniyaUnita
    {
        $workflow = $this->getWorkflowById($workflowId);
        return $this->makeResult($workflow, ResultatUdaleniyaUnita::class);
    }

    public function nachatPodgotovkuUnita(string $workflowId,NachatPodgotovkuUnita $command): string
    {
        $runnerState = $this->resolveRunnerState($command->TaskQueue, $command->RunnerId, $command->Id);
        $taskQueue = $runnerState->getTaskQueue();

        $this->proveritResursy($runnerState);
        $this->zaregistrirovatProgressToken($workflowId);

        $workflow = $this->workflowClient->newWorkflowStub(
            NachatPodgotovkuUnitaWorkflow::class,
            WorkflowOptions::new()
                ->withWorkflowId($workflowId)
                ->withTaskQueue(\App\App\Infra\Workflow\WorkflowClientFactory::monoQueueName)
                ->withWorkflowExecutionTimeout(CarbonInterval::minute(10))
        );

        $run = $this->workflowClient->start($workflow, $command, $taskQueue);
        return $run->getExecution()->getID();
    }

    /**
     * Пересоздание IDE-сайдкара подготовленного юнита с новым режимом.
     */
    public function izmenitRezhimRedaktoraUnita(string $workflowId, IzmenitRezhimRedaktoraUnita $command): string
    {
        $runnerState = $this->resolveRunnerState($command->TaskQueue, $command->RunnerId, $command->UnitId);
        $taskQueue = $runnerState->getTaskQueue();

        $this->proveritResursy($runnerState);
        $this->zaregistrirovatProgressToken($workflowId);

        $workflow = $this->workflowClient->newWorkflowStub(
            IzmenitRezhimRedaktoraUnitaWorkflow::class,
            WorkflowOptions::new()
                ->withWorkflowId($workflowId)
                ->withTaskQueue(\App\App\Infra\Workflow\WorkflowClientFactory::monoQueueName)
                ->withWorkflowExecutionTimeout(CarbonInterval::minute(10))
        );

        $run = $this->workflowClient->start($workflow, $command, $taskQueue);
        return $run->getExecution()->getID();
    }

    /**
     * Подготовка редакторской сессии юнита под личным токеном пользователя.
     */
    public function podgotovitSessiyuRedaktora(string $workflowId, PodgotovitSessiyuRedaktora $command): string
    {
        $runnerState = $this->resolveRunnerState($command->TaskQueue, $command->RunnerId, $command->UnitId);
        $taskQueue = $runnerState->getTaskQueue();

        $this->proveritResursy($runnerState);
        $this->zaregistrirovatProgressToken($workflowId);

        $workflow = $this->workflowClient->newWorkflowStub(
            PodgotovitSessiyuRedaktoraWorkflow::class,
            WorkflowOptions::new()
                ->withWorkflowId($workflowId)
                ->withTaskQueue(\App\App\Infra\Workflow\WorkflowClientFactory::monoQueueName)
                ->withWorkflowExecutionTimeout(CarbonInterval::minute(10))
        );

        $run = $this->workflowClient->start($workflow, $command, $taskQueue);
        return $run->getExecution()->getID();
    }

    public function nachatObnovlenieUnita(string $workflowId,NachatObnovlenieUnita $command): string
    {
        $runnerState = $this->resolveRunnerState($command->TaskQueue, $command->RunnerId, $command->Id);
        $taskQueue = $runnerState->getTaskQueue();

        $this->proveritResursy($runnerState);
        $this->zaregistrirovatProgressToken($workflowId);

        $workflow = $this->workflowClient->newWorkflowStub(
            NachatObnovlenieUnitaWorkflow::class,
            WorkflowOptions::new()
                ->withWorkflowId($workflowId)
                ->withTaskQueue(\App\App\Infra\Workflow\WorkflowClientFactory::monoQueueName)
                ->withWorkflowExecutionTimeout(CarbonInterval::minute(10))
        );

        $run = $this->workflowClient->start($workflow, $command, $taskQueue);
        return $run->getExecution()->getID();
    }

    public function nachatSbrosPodgotovkiUnita(string $workflowId,NachatSbrosPodgotovkiUnita $command): string
    {
        $runnerState = $this->resolveRunnerState($command->TaskQueue, $command->RunnerId, $command->UnitId);
        $taskQueue = $runnerState->getTaskQueue();

        $this->zaregistrirovatProgressToken($workflowId);

        $workflow = $this->workflowClient->newWorkflowStub(
            NachatSbrosPodgotovkiWorkflow::class,
            WorkflowOptions::new()
                ->withWorkflowId($workflowId)
                ->withTaskQueue(\App\App\Infra\Workflow\WorkflowClientFactory::monoQueueName)
                ->withWorkflowExecutionTimeout(CarbonInterval::minute(10))
        );

        $run = $this->workflowClient->start($workflow, $command, $taskQueue);
        return $run->getExecution()->getID();
    }

    public function nachatZapuskUnita(string $workflowId,NachatZapuskUnita $command): string
    {
        $runnerState = $this->resolveRunnerState($command->TaskQueue, $command->RunnerId, $command->Id);
        $taskQueue = $runnerState->getTaskQueue();

        $this->proveritResursy($runnerState);

        $percentFree = $runnerState->getMemoryInfo()->getFreePercent();

        if ($percentFree < $this->minFreeMemoryPercent) {
            throw new \DomainException('runner.memory_free_is_to_small');
        }

        $this->zaregistrirovatProgressToken($workflowId);

        $workflow = $this->workflowClient->newWorkflowStub(
            NachatZapuskUnitaWorkflow::class,
            WorkflowOptions::new()
                ->withWorkflowId($workflowId)
                ->withTaskQueue(\App\App\Infra\Workflow\WorkflowClientFactory::monoQueueName)
                ->withWorkflowTaskTimeout(CarbonInterval::minute(1))
                ->withWorkflowExecutionTimeout(CarbonInterval::minute(60))
        );

        $run = $this->workflowClient->start($workflow, $command, $taskQueue);
        return $run->getExecution()->getID();
    }

    public function nachatOstanovkuUnita(string $workflowId,NachatOstanovkuUnita $command): string
    {
        $runnerState = $this->resolveRunnerState($command->TaskQueue, $command->RunnerId, $command->Id);
        $taskQueue = $runnerState->getTaskQueue();

        $this->zaregistrirovatProgressToken($workflowId);

        $workflow = $this->workflowClient->newWorkflowStub(
            NachatOstanovkuUnitaWorkflow::class,
            WorkflowOptions::new()
                ->withWorkflowId($workflowId)
                ->withTaskQueue(\App\App\Infra\Workflow\WorkflowClientFactory::monoQueueName)
                ->withWorkflowExecutionTimeout(CarbonInterval::minute(10))
        );

        $run = $this->workflowClient->start($workflow, $command, $taskQueue);
        return $run->getExecution()->getID();
    }


    public function nachatDeistvieUnita(string $workflowId,NachatDeistvieUnita $command): string
    {
        $runnerState = $this->resolveRunnerState($command->TaskQueue, $command->RunnerId, $command->Id);
        $taskQueue = $runnerState->getTaskQueue();

        $this->zaregistrirovatProgressToken($workflowId);

        $workflow = $this->workflowClient->newWorkflowStub(
            NachatDeistvieUnitaWorkflow::class,
            WorkflowOptions::new()
                ->withWorkflowId($workflowId)
                ->withTaskQueue(\App\App\Infra\Workflow\WorkflowClientFactory::monoQueueName)
                ->withWorkflowExecutionTimeout(CarbonInterval::minute(10))
        );

        $run = $this->workflowClient->start($workflow, $command, $taskQueue);
        return $run->getExecution()->getID();
    }

    public function poluchitResultatPodgotovki(string $workflowId): ?ResultatPodgotovkiUnita
    {
        $workflow = $this->getWorkflowById($workflowId);
        $result = $this->makeResult($workflow, ResultatPodgotovkiUnita::class);

        if ($result === null) {
            return null;
        }

        if (!empty($result->ResponseId)) {
            $responseContent = $this->redisService->get($result->ResponseId);

            if (!empty($responseContent)) {
                $Steps = $this->serializer->deserialize($responseContent, Step::class.'[]', 'json');
                $result->Steps = json_decode(json_encode($Steps, JSON_THROW_ON_ERROR), true);
            }
        }

        return $result;
    }

    public function poluchitResultatObnovleniyaUnita(string $workflowId): ?ResultatObnovleniyaUnita
    {
        $workflow = $this->getWorkflowById($workflowId);
        return $this->makeResultWithConfig($workflow, ResultatObnovleniyaUnita::class);
    }

    public function poluchitResultatSbrosaPodgotovkiUnita(string $workflowId): ?ResultatSbrosaPodgotovkiUnita
    {
        $workflow = $this->getWorkflowById($workflowId);
        $result = $this->makeResult($workflow, ResultatSbrosaPodgotovkiUnita::class);

        if ($result === null) {
            return null;
        }

        if (!empty($result->ResponseId)) {
            $responseContent = $this->redisService->get($result->ResponseId);

            if (!empty($responseContent)) {
                $Steps = $this->serializer->deserialize($responseContent, Step::class.'[]', 'json');
                $result->Steps = json_decode(json_encode($Steps, JSON_THROW_ON_ERROR), true);
            }
        }

        return $result;
    }

    public function poluchitResultatZapuskaUnita(string $workflowId): ?ResultatZapuskaUnita
    {
        $workflow = $this->getWorkflowById($workflowId);
        $result = $this->makeResult($workflow, ResultatZapuskaUnita::class);

        if ($result === null) {
            return null;
        }

        if (!empty($result->ResponseId)) {
            $responseContent = $this->redisService->get($result->ResponseId);

            if (!empty($responseContent)) {
                $Steps = $this->serializer->deserialize($responseContent, Step::class.'[]', 'json');
                $result->Steps = json_decode(json_encode($Steps, JSON_THROW_ON_ERROR), true);
            }
        }

        return $result;
    }

    public function poluchitResultatOstanovkiUnita(string $workflowId): ?ResultatOstanovkiUnita
    {
        $workflow = $this->getWorkflowById($workflowId);
        $result = $this->makeResult($workflow, ResultatOstanovkiUnita::class);

        if ($result === null) {
            return null;
        }

        if (!empty($result->ResponseId)) {
            $responseContent = $this->redisService->get($result->ResponseId);

            if (!empty($responseContent)) {
                $Steps = $this->serializer->deserialize($responseContent, Step::class.'[]', 'json');
                $result->Steps = json_decode(json_encode($Steps, JSON_THROW_ON_ERROR), true);
            }
        }

        return $result;
    }

    /**
     * @param string $workflowId
     * @return \Temporal\Client\WorkflowStubInterface
     * @throws \Exception
     */
    /**
     * Предпроверка ресурсов сервера перед ресурсоёмкой операцией.
     *
     * Авторитетный гейт находится на раннере (там реальный снимок ресурсов),
     * проверка здесь нужна, чтобы пользователь получил локализованную ошибку
     * сразу, не дожидаясь выполнения Temporal-активности. Если состояние
     * раннера недоступно, проверка пропускается.
     */
    private function proveritResursy(RunnerState $runnerState): void
    {
        if ($runnerState->isResourcesBlocked()) {
            throw new \DomainException('runner.resources.blocker');
        }
    }

    /**
     * Разрешает раннер операции и его Temporal-очередь.
     *
     * `taskQueue` зарегистрированного раннера всегда равен `runnerId`
     * (контракт §4); для уже привязанного юнита используется `unit_runner`,
     * для остальных операций — выбор самого свободного живого раннера.
     *
     * @throws \DomainException `runner.not_found` | `runner.inactive`
     */
    private function resolveRunnerState(
        ?string $taskQueue = null,
        ?string $runnerId = null,
        ?string $unitId = null,
    ): RunnerState
    {
        return $this->runnerSelectionService->resolve($taskQueue, $runnerId, $unitId);
    }

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
     * @return T|null null — workflow ещё не завершён, результата пока нет.
     * @throws \Throwable
     */
    private function makeResult(\Temporal\Client\WorkflowStubInterface $workflow, string $type): ?object
    {
        try {
            $result = $workflow->getResult($type, 5);
            return $result;
        } catch (TimeoutException) {
            // Deadline reached — это штатное «результата ещё нет», а не сбой.
            // Раньше исключение уходило в общий catch и пробрасывалось наружу,
            // где превращалось в ошибку обработки задания (и сыпалось в лог
            // тысячами строк при каждом опросе незавершённого workflow).
            return null;
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
     * @return T|null null — workflow ещё не завершён, результата пока нет.
     * @throws \Throwable
     */
    private function makeResultWithConfig(\Temporal\Client\WorkflowStubInterface $workflow, string $type): ?object
    {
        try {
            $result = $workflow->getResult($type, 5);
            return $result;
        } catch (TimeoutException) {
            // См. makeResult(): истёкший deadline — это «ещё не готово».
            return null;
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

    public function poluchitResultatDeistviya(string $workflowId): ?ResultatDeistviyaUnita
    {
        $workflow = $this->getWorkflowById($workflowId);
        $result = $this->makeResult($workflow, ResultatDeistviyaUnita::class);

        if ($result === null) {
            return null;
        }

        if (!empty($result->ResponseId)) {
            $responseContent = $this->redisService->get($result->ResponseId);

            if (!empty($responseContent)) {
                $Steps = $this->serializer->deserialize($responseContent, Step::class.'[]', 'json');
                $result->Steps = json_decode(json_encode($Steps, JSON_THROW_ON_ERROR), true);
            }
        }

        return $result;
    }

    public function nachatHodOpenCode(string $workflowId, NachatHodOpenCode $command): string
    {
        $runnerState = $this->resolveRunnerState($command->TaskQueue, $command->RunnerId, $command->UnitId);
        $taskQueue = $runnerState->getTaskQueue();

        // Реквизиты забираются до старта workflow: если ключ не расшифровывается
        // или реквизиты чужие, ошибка возвращается сразу, без «висящего» workflow.
        $rekvizity = $this->rekvizityOpenCode->poluchitRekvizity(
            $command->AccountId,
            $command->CredentialId,
            $command->Model,
        );

        $this->zaregistrirovatProgressToken($workflowId);

        $activityCommand = new VipolnitHodOpenCode(
            $command->SessionId,
            $command->Prompt,
            $command->Answers,
            $command->History,
            $rekvizity->url,
            $rekvizity->model,
            $rekvizity->apiKey,
            $command->ProjectId,
            $command->UnitId,
            $command->Units,
            $command->WorkspaceId,
            $command->WorkspaceReadOnly,
            $command->WorkspaceProjects,
            $command->ReadOnly,
            $command->Token,
            RunnerId: $runnerState->getId(),
            TaskQueue: $taskQueue,
        );

        $workflow = $this->workflowClient->newWorkflowStub(
            OpenCodeTurnWorkflow::class,
            WorkflowOptions::new()
                ->withWorkflowId($workflowId)
                ->withTaskQueue(\App\App\Infra\Workflow\WorkflowClientFactory::monoQueueName)
                ->withWorkflowExecutionTimeout(CarbonInterval::minute(15))
        );

        $run = $this->workflowClient->start($workflow, $activityCommand, $taskQueue);
        return $run->getExecution()->getID();
    }

    public function poluchitResultatHodaOpenCode(string $workflowId): ?ResultatHodaOpenCode
    {
        $workflow = $this->getWorkflowById($workflowId);

        try {
            return $workflow->getResult(ResultatHodaOpenCode::class, 5);
        } catch (TimeoutException) {
            // Истёкший deadline — это «ход ещё выполняется», а не ошибка.
            return null;
        } catch (WorkflowServiceException $e) {
            return $this->oshibkaHoda($e->getPrevious()?->getMessage() ?? 'runner.unknown_error_when_make_result');
        } catch (WorkflowExecutionFailedException|WorkflowFailedException $exp) {
            return $this->oshibkaHoda($exp->getMessage());
        }
    }

    /**
     * Результат-ошибка хода OpenCode.
     *
     * Отдельный фабричный метод, потому что `makeResult()` создаёт результат
     * по контракту `(int $Success, array $Steps)`, а у хода другая структура:
     * клиенту нужен машинный `ErrorKey` вместо списка шагов.
     */
    private function oshibkaHoda(string $errorKey): ResultatHodaOpenCode
    {
        return new ResultatHodaOpenCode(false, ResultatHodaOpenCode::KIND_ERROR, [], null, $errorKey);
    }

    public function proveritKonteinerUnita(ProveritKonteinerUnita $command): ResultatProverkiKonteineraUnita
    {
        $runnerState = $this->resolveRunnerState($command->TaskQueue, $command->RunnerId, $command->Id);
        $taskQueue = $runnerState->getTaskQueue();

        $workflow = $this->workflowClient->newWorkflowStub(
            ProveritKonteinerUnitaWorkflow::class,
            WorkflowOptions::new()
                ->withTaskQueue(\App\App\Infra\Workflow\WorkflowClientFactory::monoQueueName)
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(30))
        );
        $result = $workflow->proverit($command, $taskQueue);

        return $result;
    }

    public function zapustitWorkspace(string $workflowId, ZapustitWorkspaceZapros $command): string
    {
        $runnerState = $this->resolveRunnerState($command->TaskQueue, $command->RunnerId);
        $taskQueue = $runnerState->getTaskQueue();

        $this->proveritResursy($runnerState);

        // Токен не приходит от вызывающей стороны: URL клона дозапрашивается
        // у Unitman здесь, и только обогащённая команда уходит в workflow.
        $projects = [];

        foreach ($command->Projects as $project) {
            $projects[] = new ProektWorkspaceCommand(
                $project->ProjectId,
                $project->ProjectName,
                $project->ProjectCode,
                $this->urlKlonaProekta->poluchitUrlKlona($project->ProjectId),
                $project->CanPush,
                $project->MainBranch,
            );
        }

        $activityCommand = new ZapustitWorkspace(
            $command->WorkspaceId,
            $command->WorkspaceName,
            $command->WorkspaceSlug,
            $command->Branch,
            $command->ReadOnly,
            $command->MemoryLimit,
            $projects,
            RunnerId: $runnerState->getId(),
            TaskQueue: $taskQueue,
        );

        $this->zaregistrirovatProgressToken($workflowId);

        $workflow = $this->workflowClient->newWorkflowStub(
            ZapustitWorkspaceWorkflow::class,
            WorkflowOptions::new()
                ->withWorkflowId($workflowId)
                ->withTaskQueue(\App\App\Infra\Workflow\WorkflowClientFactory::monoQueueName)
                ->withWorkflowExecutionTimeout(CarbonInterval::minute(30))
        );

        $run = $this->workflowClient->start($workflow, $activityCommand, $taskQueue);
        return $run->getExecution()->getID();
    }

    public function poluchitResultatZapuskaWorkspace(string $workflowId): ?ResultatZapuskaWorkspace
    {
        $workflow = $this->getWorkflowById($workflowId);
        return $this->makeResult($workflow, ResultatZapuskaWorkspace::class);
    }

    public function ostanovitWorkspace(string $workflowId, OstanovitWorkspace $command): string
    {
        $runnerState = $this->resolveRunnerState($command->TaskQueue, $command->RunnerId);
        $taskQueue = $runnerState->getTaskQueue();

        $this->zaregistrirovatProgressToken($workflowId);

        $workflow = $this->workflowClient->newWorkflowStub(
            OstanovitWorkspaceWorkflow::class,
            WorkflowOptions::new()
                ->withWorkflowId($workflowId)
                ->withTaskQueue(\App\App\Infra\Workflow\WorkflowClientFactory::monoQueueName)
                ->withWorkflowExecutionTimeout(CarbonInterval::minute(10))
        );

        $run = $this->workflowClient->start($workflow, $command, $taskQueue);
        return $run->getExecution()->getID();
    }

    public function poluchitResultatOstanovkiWorkspace(string $workflowId): ?ResultatOstanovkiWorkspace
    {
        $workflow = $this->getWorkflowById($workflowId);
        return $this->makeResult($workflow, ResultatOstanovkiWorkspace::class);
    }

    public function udalitWorkspace(string $workflowId, UdalitWorkspace $command): string
    {
        $runnerState = $this->resolveRunnerState($command->TaskQueue, $command->RunnerId);
        $taskQueue = $runnerState->getTaskQueue();

        $this->zaregistrirovatProgressToken($workflowId);

        $workflow = $this->workflowClient->newWorkflowStub(
            UdalitWorkspaceWorkflow::class,
            WorkflowOptions::new()
                ->withWorkflowId($workflowId)
                ->withTaskQueue(\App\App\Infra\Workflow\WorkflowClientFactory::monoQueueName)
                ->withWorkflowExecutionTimeout(CarbonInterval::minute(10))
        );

        $run = $this->workflowClient->start($workflow, $command, $taskQueue);
        return $run->getExecution()->getID();
    }

    public function poluchitResultatUdaleniyaWorkspace(string $workflowId): ?ResultatUdaleniyaWorkspace
    {
        $workflow = $this->getWorkflowById($workflowId);
        return $this->makeResult($workflow, ResultatUdaleniyaWorkspace::class);
    }

    public function proveritIzmeneniyaWorkspace(
        ProveritIzmeneniyaWorkspace $command,
    ): ResultatProverkiIzmeneniyWorkspace {
        $runnerState = $this->resolveRunnerState($command->TaskQueue, $command->RunnerId);
        $taskQueue = $runnerState->getTaskQueue();

        $workflow = $this->workflowClient->newWorkflowStub(
            ProveritIzmeneniyaWorkspaceWorkflow::class,
            WorkflowOptions::new()
                ->withTaskQueue(\App\App\Infra\Workflow\WorkflowClientFactory::monoQueueName)
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(30))
        );

        return $workflow->proverit($command, $taskQueue);
    }
}
