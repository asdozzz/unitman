<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Acl;

use App\OpenCode\Acl\RunnerAdapter;
use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\Model\WorkspaceKontekst;
use App\OpenCode\Business\Port\UuidGenerator;
use App\OpenCode\Business\Port\WorkspaceRunnerRepository;
use App\OpenCode\Tests\Support\FabrikaWorkspace;
use App\OpenCode\Tests\Support\InMemoryWorkspaceRunnerRepository;
use App\Runner\Api\RunnerApiInterface;
use App\Runner\Business\Command\NachatHodOpenCode;
use App\Runner\Business\Command\Workspace\OstanovitWorkspace;
use App\Runner\Business\Command\Workspace\UdalitWorkspace;
use App\Runner\Business\Command\Workspace\ZapustitWorkspaceZapros;
use App\Runner\Business\Model\RunnerState;
use App\Runner\Business\Model\RunnerState\MemoryInfo;
use App\Runner\Business\UseCase\RunnerSelectionService;
use App\Runner\Infra\Adapter\DefaultRunnerSelector;
use App\Runner\Tests\Support\InMemoryRunnerRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Привязка workspace к раннеру и маршрутизация его операций.
 */
final class RunnerAdapterWorkspaceBindingTest extends TestCase
{
    #[Test]
    public function pervyy_zapusk_privyazyvaet_workspace_i_marshruet_na_tot_zhe_runner(): void
    {
        $runners = new InMemoryRunnerRepository();
        $runners->save($this->liveRunner('runner-1'));
        $bindings = new InMemoryWorkspaceRunnerRepository();

        $command = null;

        $runnerApi = $this->createMock(RunnerApiInterface::class);
        $runnerApi->expects(self::once())
            ->method('zapustitWorkspace')
            ->willReturnCallback(
                static function (string $workflowId, ZapustitWorkspaceZapros $received) use (&$command): string {
                    $command = $received;

                    return $workflowId;
                },
            );

        $uuid = $this->createMock(UuidGenerator::class);
        $uuid->method('makeGuid')->willReturn('wf-1');

        $adapter = new RunnerAdapter(
            $runnerApi,
            $uuid,
            $bindings,
            new RunnerSelectionService($runners, new DefaultRunnerSelector(), new \App\Runner\Tests\Support\InMemoryUnitRunnerRepository()),
        );

        $adapter->zapustit(FabrikaWorkspace::vStatuse(StatusWorkspace::RUNNING));

        self::assertSame('runner-1', $bindings->findRunnerIdByWorkspaceId('workspace-1'));
        self::assertInstanceOf(ZapustitWorkspaceZapros::class, $command);
        self::assertSame($bindings->findRunnerIdByWorkspaceId('workspace-1'), $command->RunnerId);
        self::assertSame('runner-1', $command->TaskQueue);
    }

    #[Test]
    public function uzhe_privyazanniy_workspace_zapuskaetsya_na_svoem_runner_bez_rebind(): void
    {
        $runners = new InMemoryRunnerRepository();
        $runners->save($this->liveRunner('runner-1'));
        $runners->save($this->liveRunner('runner-2'));
        $bindings = new InMemoryWorkspaceRunnerRepository(['workspace-1' => 'runner-2']);

        $command = null;

        $runnerApi = $this->createMock(RunnerApiInterface::class);
        $runnerApi->expects(self::once())
            ->method('zapustitWorkspace')
            ->willReturnCallback(
                static function (string $workflowId, ZapustitWorkspaceZapros $received) use (&$command): string {
                    $command = $received;

                    return $workflowId;
                },
            );

        $adapter = new RunnerAdapter(
            $runnerApi,
            $this->createMock(UuidGenerator::class),
            $bindings,
            new RunnerSelectionService($runners, new DefaultRunnerSelector(), new \App\Runner\Tests\Support\InMemoryUnitRunnerRepository()),
        );

        $adapter->zapustit(FabrikaWorkspace::vStatuse(StatusWorkspace::RUNNING));

        self::assertSame('runner-2', $bindings->findRunnerIdByWorkspaceId('workspace-1'));
        self::assertInstanceOf(ZapustitWorkspaceZapros::class, $command);
        self::assertSame('runner-2', $command->RunnerId);
        self::assertSame('runner-2', $command->TaskQueue);
    }

    #[Test]
    public function vosstanovlenie_privyazyvaet_workspace_i_marshruet_na_tot_zhe_runner(): void
    {
        $runners = new InMemoryRunnerRepository();
        $runners->save($this->liveRunner('runner-1'));
        $bindings = new InMemoryWorkspaceRunnerRepository();

        $command = null;

        $runnerApi = $this->createMock(RunnerApiInterface::class);
        $runnerApi->expects(self::once())
            ->method('zapustitWorkspace')
            ->willReturnCallback(
                static function (string $workflowId, ZapustitWorkspaceZapros $received) use (&$command): string {
                    $command = $received;

                    return $workflowId;
                },
            );

        $adapter = new RunnerAdapter(
            $runnerApi,
            $this->createMock(UuidGenerator::class),
            $bindings,
            new RunnerSelectionService($runners, new DefaultRunnerSelector(), new \App\Runner\Tests\Support\InMemoryUnitRunnerRepository()),
        );

        $adapter->vosstanovit(FabrikaWorkspace::vStatuse(StatusWorkspace::RUNNING));

        self::assertSame('runner-1', $bindings->findRunnerIdByWorkspaceId('workspace-1'));
        self::assertInstanceOf(ZapustitWorkspaceZapros::class, $command);
        self::assertSame($bindings->findRunnerIdByWorkspaceId('workspace-1'), $command->RunnerId);
        self::assertSame('runner-1', $command->TaskQueue);
    }

    #[Test]
    public function neaktivniy_privyazanniy_runner_na_zapuske_daet_oshibku_bez_rebind(): void
    {
        $runners = new InMemoryRunnerRepository();
        $runners->save($this->liveRunner('runner-1'));
        $runners->save(new RunnerState('runner-dead', 'runner-dead', false));
        $bindings = new InMemoryWorkspaceRunnerRepository(['workspace-1' => 'runner-dead']);

        $runnerApi = $this->createMock(RunnerApiInterface::class);
        $runnerApi->expects(self::never())->method('zapustitWorkspace');

        $adapter = new RunnerAdapter(
            $runnerApi,
            $this->createMock(UuidGenerator::class),
            $bindings,
            new RunnerSelectionService($runners, new DefaultRunnerSelector(), new \App\Runner\Tests\Support\InMemoryUnitRunnerRepository()),
        );

        try {
            $adapter->zapustit(FabrikaWorkspace::vStatuse(StatusWorkspace::RUNNING));
            self::fail('Ожидалась ошибка runner.inactive');
        } catch (\DomainException $error) {
            self::assertSame('runner.inactive', $error->getMessage());
        }

        self::assertSame('runner-dead', $bindings->findRunnerIdByWorkspaceId('workspace-1'));
    }

    #[Test]
    public function ostanovka_idet_na_privyazanniy_runner(): void
    {
        $runners = new InMemoryRunnerRepository();
        $runners->save($this->liveRunner('runner-1'));
        $runners->save($this->liveRunner('runner-2'));
        $bindings = new InMemoryWorkspaceRunnerRepository(['workspace-1' => 'runner-2']);

        $command = null;

        $runnerApi = $this->createMock(RunnerApiInterface::class);
        $runnerApi->expects(self::once())
            ->method('ostanovitWorkspace')
            ->willReturnCallback(
                static function (string $workflowId, OstanovitWorkspace $received) use (&$command): string {
                    $command = $received;

                    return $workflowId;
                },
            );

        $adapter = new RunnerAdapter(
            $runnerApi,
            $this->createMock(UuidGenerator::class),
            $bindings,
            new RunnerSelectionService($runners, new DefaultRunnerSelector(), new \App\Runner\Tests\Support\InMemoryUnitRunnerRepository()),
        );

        $adapter->ostanovit(FabrikaWorkspace::vStatuse(StatusWorkspace::RUNNING));

        self::assertInstanceOf(OstanovitWorkspace::class, $command);
        self::assertSame('runner-2', $command->RunnerId);
        self::assertSame('runner-2', $command->TaskQueue);
    }

    #[Test]
    public function neaktivniy_privyazanniy_runner_daet_oshibku_bez_rebind(): void
    {
        $runners = new InMemoryRunnerRepository();
        $runners->save($this->liveRunner('runner-1'));
        $runners->save(new RunnerState('runner-dead', 'runner-dead', false));
        $bindings = new InMemoryWorkspaceRunnerRepository(['workspace-1' => 'runner-dead']);

        $runnerApi = $this->createMock(RunnerApiInterface::class);
        $runnerApi->expects(self::never())->method('ostanovitWorkspace');

        $adapter = new RunnerAdapter(
            $runnerApi,
            $this->createMock(UuidGenerator::class),
            $bindings,
            new RunnerSelectionService($runners, new DefaultRunnerSelector(), new \App\Runner\Tests\Support\InMemoryUnitRunnerRepository()),
        );

        try {
            $adapter->ostanovit(FabrikaWorkspace::vStatuse(StatusWorkspace::RUNNING));
            self::fail('Ожидалась ошибка runner.inactive');
        } catch (\DomainException $error) {
            self::assertSame('runner.inactive', $error->getMessage());
        }

        self::assertSame('runner-dead', $bindings->findRunnerIdByWorkspaceId('workspace-1'));
    }

    #[Test]
    public function nachatHod_na_nesvyazannom_workspace_privyazyvaet_odin_raz_i_marshruet_na_sohranenniy_runner(): void
    {
        $runners = new InMemoryRunnerRepository();
        $runners->save($this->liveRunner('runner-1'));
        $runners->save($this->liveRunner('runner-2'));
        $bindings = new CountingWorkspaceRunnerRepository();

        $command = null;

        $runnerApi = $this->createMock(RunnerApiInterface::class);
        $runnerApi->expects(self::once())
            ->method('nachatHodOpenCode')
            ->willReturnCallback(
                static function (string $workflowId, NachatHodOpenCode $received) use (&$command): string {
                    $command = $received;

                    return $workflowId;
                },
            );

        $adapter = new RunnerAdapter(
            $runnerApi,
            $this->createMock(UuidGenerator::class),
            $bindings,
            $this->selection($runners),
        );

        $adapter->nachatHod(
            'wf-1',
            'account-1',
            'credential-1',
            null,
            [],
            'prompt',
            [],
            [],
            new WorkspaceKontekst('workspace-1', false, []),
        );

        $privyazanniy = $bindings->findRunnerIdByWorkspaceId('workspace-1');

        self::assertSame(1, $bindings->bindCount, 'Привязка должна произойти ровно один раз');
        self::assertNotNull($privyazanniy);
        self::assertInstanceOf(NachatHodOpenCode::class, $command);
        self::assertSame($privyazanniy, $command->RunnerId);
        self::assertSame($privyazanniy, $command->TaskQueue);
    }

    #[Test]
    public function nachatHod_na_privyazannom_workspace_marshruet_na_svoego_runnera_bez_rebind(): void
    {
        $runners = new InMemoryRunnerRepository();
        $runners->save($this->liveRunner('runner-1'));
        $runners->save($this->liveRunner('runner-2'));
        $bindings = new CountingWorkspaceRunnerRepository(['workspace-1' => 'runner-2']);

        $command = null;

        $runnerApi = $this->createMock(RunnerApiInterface::class);
        $runnerApi->expects(self::once())
            ->method('nachatHodOpenCode')
            ->willReturnCallback(
                static function (string $workflowId, NachatHodOpenCode $received) use (&$command): string {
                    $command = $received;

                    return $workflowId;
                },
            );

        $adapter = new RunnerAdapter(
            $runnerApi,
            $this->createMock(UuidGenerator::class),
            $bindings,
            $this->selection($runners),
        );

        $adapter->nachatHod(
            'wf-1',
            'account-1',
            'credential-1',
            null,
            [],
            'prompt',
            [],
            [],
            new WorkspaceKontekst('workspace-1', false, []),
        );

        self::assertSame(0, $bindings->bindCount, 'Привязанный workspace не перебиндивается');
        self::assertInstanceOf(NachatHodOpenCode::class, $command);
        self::assertSame('runner-2', $command->RunnerId);
        self::assertSame('runner-2', $command->TaskQueue);
    }

    #[Test]
    public function nachatHod_na_privyazannom_neaktivnom_runner_daet_oshibku_bez_rebind(): void
    {
        $runners = new InMemoryRunnerRepository();
        $runners->save($this->liveRunner('runner-1'));
        $runners->save(new RunnerState('runner-dead', 'runner-dead', false));
        $bindings = new CountingWorkspaceRunnerRepository(['workspace-1' => 'runner-dead']);

        $runnerApi = $this->createMock(RunnerApiInterface::class);
        $runnerApi->expects(self::never())->method('nachatHodOpenCode');

        $adapter = new RunnerAdapter(
            $runnerApi,
            $this->createMock(UuidGenerator::class),
            $bindings,
            $this->selection($runners),
        );

        try {
            $adapter->nachatHod(
                'wf-1',
                'account-1',
                'credential-1',
                null,
                [],
                'prompt',
                [],
                [],
                new WorkspaceKontekst('workspace-1', false, []),
            );
            self::fail('Ожидалась ошибка runner.inactive');
        } catch (\DomainException $error) {
            self::assertSame('runner.inactive', $error->getMessage());
        }

        self::assertSame(0, $bindings->bindCount);
        self::assertSame('runner-dead', $bindings->findRunnerIdByWorkspaceId('workspace-1'));
    }

    #[Test]
    public function nachatHod_bez_zhivogo_runner_daet_runner_not_found_i_ne_privyazyvaet(): void
    {
        $runners = new InMemoryRunnerRepository();
        // Раннер есть, но не жив (inactive): свободный выбор невозможен.
        $runners->save(new RunnerState('runner-dead', 'runner-dead', false));
        $bindings = new CountingWorkspaceRunnerRepository();

        $runnerApi = $this->createMock(RunnerApiInterface::class);
        $runnerApi->expects(self::never())->method('nachatHodOpenCode');

        $adapter = new RunnerAdapter(
            $runnerApi,
            $this->createMock(UuidGenerator::class),
            $bindings,
            $this->selection($runners),
        );

        try {
            $adapter->nachatHod(
                'wf-1',
                'account-1',
                'credential-1',
                null,
                [],
                'prompt',
                [],
                [],
                new WorkspaceKontekst('workspace-1', false, []),
            );
            self::fail('Ожидалась ошибка runner.not_found');
        } catch (\DomainException $error) {
            self::assertSame('runner.not_found', $error->getMessage());
        }

        self::assertSame(0, $bindings->bindCount);
        self::assertNull($bindings->findRunnerIdByWorkspaceId('workspace-1'));
    }

    #[Test]
    public function ostanovka_na_nesvyazannom_workspace_privyazyvaet_i_marshruet_na_sohranenniy_runner(): void
    {
        $runners = new InMemoryRunnerRepository();
        $runners->save($this->liveRunner('runner-1'));
        $bindings = new CountingWorkspaceRunnerRepository();

        $command = null;

        $runnerApi = $this->createMock(RunnerApiInterface::class);
        $runnerApi->expects(self::once())
            ->method('ostanovitWorkspace')
            ->willReturnCallback(
                static function (string $workflowId, OstanovitWorkspace $received) use (&$command): string {
                    $command = $received;

                    return $workflowId;
                },
            );

        $adapter = new RunnerAdapter(
            $runnerApi,
            $this->createMock(UuidGenerator::class),
            $bindings,
            $this->selection($runners),
        );

        $adapter->ostanovit(FabrikaWorkspace::vStatuse(StatusWorkspace::RUNNING));

        self::assertSame(1, $bindings->bindCount);
        self::assertInstanceOf(OstanovitWorkspace::class, $command);
        self::assertSame($bindings->findRunnerIdByWorkspaceId('workspace-1'), $command->RunnerId);
        self::assertSame('runner-1', $command->TaskQueue);
    }

    #[Test]
    public function udalenie_na_nesvyazannom_workspace_privyazyvaet_i_marshruet_na_sohranenniy_runner(): void
    {
        $runners = new InMemoryRunnerRepository();
        $runners->save($this->liveRunner('runner-1'));
        $bindings = new CountingWorkspaceRunnerRepository();

        $command = null;

        $runnerApi = $this->createMock(RunnerApiInterface::class);
        $runnerApi->expects(self::once())
            ->method('udalitWorkspace')
            ->willReturnCallback(
                static function (string $workflowId, UdalitWorkspace $received) use (&$command): string {
                    $command = $received;

                    return $workflowId;
                },
            );

        $adapter = new RunnerAdapter(
            $runnerApi,
            $this->createMock(UuidGenerator::class),
            $bindings,
            $this->selection($runners),
        );

        $adapter->udalit(FabrikaWorkspace::vStatuse(StatusWorkspace::RUNNING));

        self::assertSame(1, $bindings->bindCount);
        self::assertInstanceOf(UdalitWorkspace::class, $command);
        self::assertSame($bindings->findRunnerIdByWorkspaceId('workspace-1'), $command->RunnerId);
        self::assertSame('runner-1', $command->TaskQueue);
    }

    private function selection(InMemoryRunnerRepository $runners): RunnerSelectionService
    {
        return new RunnerSelectionService(
            $runners,
            new DefaultRunnerSelector(),
            new \App\Runner\Tests\Support\InMemoryUnitRunnerRepository(),
        );
    }

    private function liveRunner(string $id): RunnerState
    {
        $runner = new RunnerState(
            $id,
            $id,
            true,
            publicAddress: 'https://' . $id . '.example.com',
        );
        $runner->ustanovitResultatRabotosposobnosti(
            true,
            [],
            new MemoryInfo(1000, 900),
            time(),
        );

        return $runner;
    }
}

/**
 * Привязки workspace в памяти с подсчётом вызовов `bind` для проверки
 * «привязать ровно один раз».
 */
final class CountingWorkspaceRunnerRepository implements WorkspaceRunnerRepository
{
    public int $bindCount = 0;

    /** @var array<string, string> workspaceId => runnerId */
    private array $bindings;

    /**
     * @param array<string, string> $bindings
     */
    public function __construct(array $bindings = [])
    {
        $this->bindings = $bindings;
    }

    public function findRunnerIdByWorkspaceId(string $workspaceId): ?string
    {
        return $this->bindings[$workspaceId] ?? null;
    }

    public function bind(string $workspaceId, string $runnerId): void
    {
        ++$this->bindCount;
        // Жёсткая привязка: существующая не перезаписывается.
        $this->bindings[$workspaceId] ??= $runnerId;
    }

    public function unbind(string $workspaceId): void
    {
        unset($this->bindings[$workspaceId]);
    }

    public function isBoundToRunner(string $workspaceId, string $runnerId): bool
    {
        return ($this->bindings[$workspaceId] ?? null) === $runnerId;
    }

    public function countByRunnerId(string $runnerId): int
    {
        return count(array_filter(
            $this->bindings,
            static fn (string $bound): bool => $bound === $runnerId,
        ));
    }
}
