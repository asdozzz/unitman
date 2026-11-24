<?php

declare(strict_types=1);

namespace App\Runner\Tests\Business\UseCase;

use App\Runner\Business\Model\RunnerState;
use App\Runner\Business\Model\RunnerState\MemoryInfo;
use App\Runner\Business\UseCase\PoluchitUgrozyRannerovQuery;
use App\Runner\Tests\Support\InMemoryRunnerRepository;
use PHPUnit\Framework\TestCase;

/**
 * Сводка для шапки: `registeredCount` — размер реестра, `count` — число
 * проблемных раннеров. Пустой реестр не является угрозой, но должен быть
 * отличим от «все раннеры в норме».
 */
final class PoluchitUgrozyRannerovQueryTest extends TestCase
{
    public function test_empty_registry_reports_zero_registered_and_no_threats(): void
    {
        $svodka = $this->query(new InMemoryRunnerRepository())->handle();

        self::assertSame(0, $svodka->registeredCount);
        self::assertFalse($svodka->hasThreats);
        self::assertSame('ok', $svodka->worstLevel);
        self::assertSame(0, $svodka->count);
        self::assertSame([], $svodka->runners);
    }

    public function test_live_runner_without_resources_is_not_a_threat(): void
    {
        $repository = new InMemoryRunnerRepository();
        $repository->save($this->liveRunner('runner-1'));

        $svodka = $this->query($repository)->handle();

        self::assertSame(1, $svodka->registeredCount);
        self::assertFalse($svodka->hasThreats);
        self::assertSame('ok', $svodka->worstLevel);
        self::assertSame(0, $svodka->count);
    }

    public function test_stale_runner_counts_as_warning_and_stays_in_registry_count(): void
    {
        $repository = new InMemoryRunnerRepository();
        $repository->save($this->liveRunner('runner-1'));
        $repository->save(RunnerState::sozdatRunner('runner-2', 'runner-2'));

        $svodka = $this->query($repository)->handle();

        self::assertSame(2, $svodka->registeredCount);
        self::assertTrue($svodka->hasThreats);
        self::assertSame('warning', $svodka->worstLevel);
        self::assertSame(1, $svodka->count);
        self::assertSame('runner-2', $svodka->runners[0]['id']);
    }

    private function query(InMemoryRunnerRepository $repository): PoluchitUgrozyRannerovQuery
    {
        return new PoluchitUgrozyRannerovQuery($repository);
    }

    private function liveRunner(string $id): RunnerState
    {
        $runner = RunnerState::sozdatZaregistrirovanniiRunnerV2(
            $id,
            'registry.example.com',
            $id . '.example.com',
            time(),
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
