<?php

declare(strict_types=1);

namespace App\Runner\Tests\Api;

use App\Runner\Api\RunnerApi;
use App\Runner\Business\Command\NachatSborkuUnita;
use App\Runner\Business\Command\RemoveProjectCommand;
use App\Runner\Business\Model\RunnerState;
use App\Runner\Business\Model\RunnerState\MemoryInfo;
use App\Runner\Business\Port\UmeetHranitProgressZadachi;
use App\Runner\Business\UseCase\RunnerSelectionService;
use App\Runner\Infra\Adapter\DefaultRunnerSelector;
use App\Runner\Tests\Support\InMemoryRunnerRepository;
use App\Runner\Tests\Support\InMemoryUnitRunnerRepository;
use PHPUnit\Framework\TestCase;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowStubInterface;

/**
 * Проверяет, что каждый старт workflow получает выбранную Temporal-очередь
 * третьим аргументом (`taskQueue`), а не legacy-константу.
 */
final class RunnerApiRoutingTest extends TestCase
{
    protected function setUp(): void
    {
        $reflection = new \ReflectionClass(WorkflowClient::class);

        if ($reflection->isFinal() || $reflection->getMethod('start')->isFinal()) {
            self::markTestSkipped('Temporal WorkflowClient::start() cannot be mocked');
        }
    }

    public function test_selected_runner_task_queue_is_passed_to_start(): void
    {
        $repository = new InMemoryRunnerRepository();
        $repository->save($this->liveRunner('runner-1', 'runner-queue-1', 90.0));

        $taskQueue = $this->captureTaskQueue(
            $repository,
            new InMemoryUnitRunnerRepository(),
            static fn (RunnerApi $api) => $api->removeProject(
                new RemoveProjectCommand('project-1'),
            ),
        );

        self::assertSame('runner-queue-1', $taskQueue);
    }

    public function test_bound_unit_routes_to_its_runner_queue(): void
    {
        $repository = new InMemoryRunnerRepository();
        $repository->save($this->liveRunner('runner-1', 'queue-1', 90.0));
        $repository->save($this->liveRunner('runner-2', 'queue-2', 10.0));

        $taskQueue = $this->captureTaskQueue(
            $repository,
            new InMemoryUnitRunnerRepository(['unit-1' => 'runner-2']),
            static fn (RunnerApi $api) => $api->nachatSborkuUnita(
                'workflow-1',
                new NachatSborkuUnita('project-1', 'unit-1', 'unit', 'https://git.example/api.git', 'main'),
            ),
        );

        self::assertSame('queue-2', $taskQueue);
    }

    public function test_command_task_queue_has_priority(): void
    {
        $repository = new InMemoryRunnerRepository();
        $repository->save($this->liveRunner('runner-1', 'queue-1', 90.0));
        $repository->save($this->liveRunner('runner-2', 'queue-2', 10.0));

        $taskQueue = $this->captureTaskQueue(
            $repository,
            new InMemoryUnitRunnerRepository(),
            static fn (RunnerApi $api) => $api->removeProject(
                new RemoveProjectCommand('project-1', TaskQueue: 'queue-2'),
            ),
        );

        self::assertSame('queue-2', $taskQueue);
    }

    private function captureTaskQueue(
        InMemoryRunnerRepository $repository,
        InMemoryUnitRunnerRepository $unitRunnerRepository,
        callable $operation,
    ): string {
        $captured = null;

        $client = $this->createMock(WorkflowClient::class);
        $client->method('newWorkflowStub')->willReturn($this->createMock(WorkflowStubInterface::class));
        $client->expects(self::once())
            ->method('start')
            ->willReturnCallback(static function (
                object $workflow,
                mixed $command,
                string $taskQueue = '',
            ) use (&$captured): never {
                $captured = $taskQueue;
                throw new \RuntimeException('stop');
            });

        $api = (new \ReflectionClass(RunnerApi::class))->newInstanceWithoutConstructor();
        $this->setProperty($api, 'workflowClient', $client);
        $this->setProperty($api, 'progressStorage', $this->createMock(UmeetHranitProgressZadachi::class));
        $this->setProperty($api, 'runnerSelectionService', new RunnerSelectionService(
            $repository,
            new DefaultRunnerSelector(),
            $unitRunnerRepository,
        ));
        $this->setProperty($api, 'minFreeMemoryPercent', 0.0);

        try {
            $operation($api);
        } catch (\RuntimeException) {
            // start() подменён исключением: дальше кода запуска нет.
        }

        self::assertIsString($captured);

        return $captured;
    }

    private function liveRunner(string $id, string $taskQueue, float $freeMemoryPercent): RunnerState
    {
        $total = 1000;
        $free = (int) round($total * $freeMemoryPercent / 100);

        $runner = new RunnerState(
            $id,
            $taskQueue,
            true,
            publicAddress: 'https://' . $id . '.example.com',
        );
        $runner->ustanovitResultatRabotosposobnosti(true, [], new MemoryInfo($total, $free), time());

        return $runner;
    }

    private function setProperty(object $object, string $name, mixed $value): void
    {
        $property = new \ReflectionProperty($object, $name);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }
}
