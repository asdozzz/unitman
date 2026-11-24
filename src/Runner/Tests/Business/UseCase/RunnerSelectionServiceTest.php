<?php

declare(strict_types=1);

namespace App\Runner\Tests\Business\UseCase;

use App\Runner\Business\Model\RunnerState;
use App\Runner\Business\Model\RunnerState\MemoryInfo;
use App\Runner\Business\Port\RunnerSelector;
use App\Runner\Business\UseCase\RunnerSelectionService;
use App\Runner\Infra\Adapter\DefaultRunnerSelector;
use App\Runner\Tests\Support\InMemoryRunnerRepository;
use App\Runner\Tests\Support\InMemoryUnitRunnerRepository;
use PHPUnit\Framework\TestCase;

final class RunnerSelectionServiceTest extends TestCase
{
    public function test_bound_unit_routes_to_its_runner_even_if_another_is_freer(): void
    {
        $repository = new InMemoryRunnerRepository();
        $repository->save($this->liveRunner('runner-1', 90.0));
        $repository->save($this->liveRunner('runner-2', 10.0));

        $present = new InMemoryUnitRunnerRepository(['unit-1' => 'runner-1']);
        $service = new RunnerSelectionService($repository, new DefaultRunnerSelector(), $present);

        self::assertSame('runner-1', $service->resolve(null, null, 'unit-1')->getId());
    }

    public function test_explicit_runner_id_has_priority_over_selection(): void
    {
        $repository = new InMemoryRunnerRepository();
        $repository->save($this->liveRunner('runner-1', 90.0));
        $repository->save($this->liveRunner('runner-2', 10.0));

        $service = new RunnerSelectionService(
            $repository,
            new DefaultRunnerSelector(),
            new InMemoryUnitRunnerRepository(),
        );

        self::assertSame('runner-2', $service->resolve(null, 'runner-2')->getId());
    }

    public function test_explicit_task_queue_is_resolved(): void
    {
        $repository = new InMemoryRunnerRepository();
        $runner = $this->liveRunner('runner-1', 50.0);
        $repository->save($runner);

        $service = new RunnerSelectionService(
            $repository,
            new DefaultRunnerSelector(),
            new InMemoryUnitRunnerRepository(),
        );

        self::assertSame('runner-1', $service->resolve('runner-1')->getId());
    }

    public function test_missing_explicit_runner_throws_runner_not_found(): void
    {
        $service = new RunnerSelectionService(
            new InMemoryRunnerRepository(),
            new DefaultRunnerSelector(),
            new InMemoryUnitRunnerRepository(),
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('runner.not_found');

        $service->resolve(null, 'unknown-runner');
    }

    public function test_inactive_explicit_runner_throws_runner_inactive(): void
    {
        $repository = new InMemoryRunnerRepository();
        $repository->save(new RunnerState('runner-1', 'runner-1', false));

        $service = new RunnerSelectionService(
            $repository,
            new DefaultRunnerSelector(),
            new InMemoryUnitRunnerRepository(),
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('runner.inactive');

        $service->resolve(null, 'runner-1');
    }

    public function test_selection_excludes_runner_without_public_address(): void
    {
        $repository = new InMemoryRunnerRepository();
        $repository->save($this->liveRunner('runner-without-address', 100.0, false));
        $repository->save($this->liveRunner('runner-with-address', 10.0));

        $service = new RunnerSelectionService(
            $repository,
            new DefaultRunnerSelector(),
            new InMemoryUnitRunnerRepository(),
        );

        self::assertSame('runner-with-address', $service->select()->getId());
    }

    public function test_selection_passes_bound_unit_counts_to_selector(): void
    {
        $repository = new InMemoryRunnerRepository();
        $repository->save($this->liveRunner('runner-1', 50.0));
        $repository->save($this->liveRunner('runner-2', 50.0));

        $present = new InMemoryUnitRunnerRepository([
            'unit-a' => 'runner-1',
            'unit-b' => 'runner-1',
            'unit-c' => 'runner-2',
        ]);

        $captured = null;
        $selector = $this->createMock(RunnerSelector::class);
        $selector->expects(self::once())
            ->method('select')
            ->willReturnCallback(static function (array $runners, array $counts) use (&$captured): RunnerState {
                $captured = $counts;

                return $runners[0];
            });

        $service = new RunnerSelectionService($repository, $selector, $present);
        $service->select();

        self::assertSame(2, $captured['runner-1']);
        self::assertSame(1, $captured['runner-2']);
    }

    public function test_no_live_runners_throws_runner_not_found(): void
    {
        $service = new RunnerSelectionService(
            new InMemoryRunnerRepository(),
            new DefaultRunnerSelector(),
            new InMemoryUnitRunnerRepository(),
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('runner.not_found');

        $service->select();
    }

    private function liveRunner(
        string $id,
        float $freeMemoryPercent,
        bool $withPublicAddress = true,
    ): RunnerState {
        $total = 1000;
        $free = (int) round($total * $freeMemoryPercent / 100);

        $runner = new RunnerState(
            $id,
            $id,
            true,
            publicAddress: $withPublicAddress ? 'https://' . $id . '.example.com' : null,
        );
        $runner->ustanovitResultatRabotosposobnosti(
            true,
            [],
            new MemoryInfo($total, $free),
            time(),
        );

        return $runner;
    }
}
