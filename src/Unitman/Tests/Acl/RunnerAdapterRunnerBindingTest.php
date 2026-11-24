<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Acl;

use App\Runner\Api\RunnerApiInterface;
use App\Runner\Business\Command\IzmenitRezhimRedaktoraUnita;
use App\Runner\Business\Model\RunnerState;
use App\Runner\Business\Model\RunnerState\MemoryInfo;
use App\Runner\Business\UseCase\RunnerSelectionService;
use App\Runner\Infra\Adapter\DefaultRunnerSelector;
use App\Runner\Tests\Support\InMemoryRunnerRepository;
use App\Runner\Tests\Support\InMemoryUnitRunnerRepository;
use App\Unitman\Acl\RunnerAdapter;
use App\Unitman\Business\Port\MediatekaService;
use App\Unitman\Business\Port\Repo\RepoRepository;
use App\Unitman\Infra\Adapter\StorageApiAdapterFactory;
use App\Unitman\Infra\Repository\Project\SqlProjectEventsRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * ACL-адаптер обязан передавать раннер юнита в исходящие команды.
 */
final class RunnerAdapterRunnerBindingTest extends TestCase
{
    #[Test]
    public function komanda_yunita_napravlyaetsya_na_privyazanniy_runner(): void
    {
        $runners = new InMemoryRunnerRepository();
        $runners->save($this->liveRunner('runner-1'));
        $runners->save($this->liveRunner('runner-2'));
        $bindings = new InMemoryUnitRunnerRepository(['unit-1' => 'runner-2']);

        $command = null;

        $runnerApi = $this->createMock(RunnerApiInterface::class);
        $runnerApi->expects(self::once())
            ->method('izmenitRezhimRedaktoraUnita')
            ->willReturnCallback(
                static function (string $workflowId, IzmenitRezhimRedaktoraUnita $received) use (&$command): string {
                    $command = $received;

                    return $workflowId;
                },
            );

        $this->adapter($runnerApi, $runners, $bindings)->izmenitRezhimRedaktora('unit-1', 'edit');

        self::assertInstanceOf(IzmenitRezhimRedaktoraUnita::class, $command);
        self::assertSame('runner-2', $command->RunnerId);
        self::assertSame('runner-2', $command->TaskQueue);
    }

    #[Test]
    public function neprivyazanniy_yunit_privyazivaetsya_i_komanda_soderzhit_runner(): void
    {
        $runners = new InMemoryRunnerRepository();
        $runners->save($this->liveRunner('runner-1'));
        $bindings = new InMemoryUnitRunnerRepository();

        $command = null;

        $runnerApi = $this->createMock(RunnerApiInterface::class);
        $runnerApi->expects(self::once())
            ->method('izmenitRezhimRedaktoraUnita')
            ->willReturnCallback(
                static function (string $workflowId, IzmenitRezhimRedaktoraUnita $received) use (&$command): string {
                    $command = $received;

                    return $workflowId;
                },
            );

        $this->adapter($runnerApi, $runners, $bindings)->izmenitRezhimRedaktora('unit-1', 'edit');

        self::assertSame('runner-1', $bindings->findRunnerIdByUnitId('unit-1'));
        self::assertSame('runner-1', $command?->RunnerId);
        self::assertSame('runner-1', $command?->TaskQueue);
    }

    #[Test]
    public function neaktivniy_privyazanniy_runner_daet_oshibku_bez_rebind(): void
    {
        $runners = new InMemoryRunnerRepository();
        $runners->save($this->liveRunner('runner-1'));
        $runners->save(new RunnerState('runner-dead', 'runner-dead', false));
        $bindings = new InMemoryUnitRunnerRepository(['unit-1' => 'runner-dead']);

        $runnerApi = $this->createMock(RunnerApiInterface::class);
        $runnerApi->expects(self::never())->method('izmenitRezhimRedaktoraUnita');

        try {
            $this->adapter($runnerApi, $runners, $bindings)->izmenitRezhimRedaktora('unit-1', 'edit');
            self::fail('Ожидалась ошибка runner.inactive');
        } catch (\DomainException $error) {
            self::assertSame('runner.inactive', $error->getMessage());
        }

        self::assertSame('runner-dead', $bindings->findRunnerIdByUnitId('unit-1'));
    }

    private function adapter(
        RunnerApiInterface $runnerApi,
        InMemoryRunnerRepository $runners,
        InMemoryUnitRunnerRepository $bindings,
    ): RunnerAdapter {
        /** @var SqlProjectEventsRepository $projectEvents */
        $projectEvents = (new \ReflectionClass(SqlProjectEventsRepository::class))->newInstanceWithoutConstructor();

        return new RunnerAdapter(
            $runnerApi,
            $this->createMock(RepoRepository::class),
            $projectEvents,
            new StorageApiAdapterFactory([]),
            $this->createMock(MediatekaService::class),
            new RunnerSelectionService($runners, new DefaultRunnerSelector(), $bindings),
            $bindings,
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
