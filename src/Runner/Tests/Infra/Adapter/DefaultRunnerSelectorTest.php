<?php

declare(strict_types=1);

namespace App\Runner\Tests\Infra\Adapter;

use App\Runner\Business\Model\RunnerState;
use App\Runner\Business\Model\RunnerState\CpuInfo;
use App\Runner\Business\Model\RunnerState\MemoryInfo;
use App\Runner\Business\Model\RunnerState\MemoryUsage;
use App\Runner\Business\Model\RunnerState\RunnerResources;
use App\Runner\Infra\Adapter\DefaultRunnerSelector;
use PHPUnit\Framework\TestCase;

final class DefaultRunnerSelectorTest extends TestCase
{
    public function test_prefers_runner_with_more_free_memory(): void
    {
        $selector = new DefaultRunnerSelector();

        $selected = $selector->select([
            $this->runner('runner-a', freeMemoryPercent: 10.0),
            $this->runner('runner-b', freeMemoryPercent: 80.0),
        ]);

        self::assertSame('runner-b', $selected->getId());
    }

    public function test_tie_break_prefers_fewer_bound_units(): void
    {
        $selector = new DefaultRunnerSelector();

        $selected = $selector->select(
            [
                $this->runner('runner-a', freeMemoryPercent: 50.0),
                $this->runner('runner-b', freeMemoryPercent: 50.0),
            ],
            ['runner-a' => 5, 'runner-b' => 1],
        );

        self::assertSame('runner-b', $selected->getId());
    }

    public function test_final_tie_break_is_lexicographically_smallest_runner_id(): void
    {
        $selector = new DefaultRunnerSelector();

        $selected = $selector->select([
            $this->runner('runner-z', freeMemoryPercent: 50.0),
            $this->runner('runner-a', freeMemoryPercent: 50.0),
        ]);

        self::assertSame('runner-a', $selected->getId());
    }

    public function test_inactive_stale_or_blocked_runners_are_excluded(): void
    {
        $selector = new DefaultRunnerSelector();

        $inactive = $this->runner('runner-inactive', freeMemoryPercent: 100.0, active: false);
        $stale = $this->runner('runner-stale', freeMemoryPercent: 100.0, healthAt: time() - 181);
        $blocked = $this->runner('runner-blocked', freeMemoryPercent: 100.0, blocked: true);
        $live = $this->runner('runner-live', freeMemoryPercent: 10.0);

        $selected = $selector->select([$inactive, $stale, $blocked, $live]);

        self::assertSame('runner-live', $selected->getId());
    }

    public function test_runner_without_public_address_is_excluded(): void
    {
        $selector = new DefaultRunnerSelector();

        $withoutAddress = $this->runner(
            'runner-without-address',
            freeMemoryPercent: 100.0,
            publicAddress: null,
        );
        $withAddress = $this->runner(
            'runner-with-address',
            freeMemoryPercent: 10.0,
            publicAddress: 'https://runner-with-address.example.com',
        );

        $selected = $selector->select([$withoutAddress, $withAddress]);

        self::assertSame('runner-with-address', $selected->getId());
    }

    public function test_throws_runner_not_found_when_no_live_runners(): void
    {
        $selector = new DefaultRunnerSelector();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('runner.not_found');

        $selector->select([
            $this->runner('runner-inactive', freeMemoryPercent: 100.0, active: false),
        ]);
    }

    private function runner(
        string $id,
        float $freeMemoryPercent,
        bool $active = true,
        ?int $healthAt = null,
        bool $blocked = false,
        ?string $publicAddress = 'https://runner.example.com',
    ): RunnerState {
        $total = 1000;
        $free = (int) round($total * $freeMemoryPercent / 100);

        $resources = new RunnerResources(
            cpu: new CpuInfo(usagePercent: 0.0),
            memory: new MemoryUsage(totalBytes: $total, usedPercent: 100.0 - $freeMemoryPercent),
            disks: [['usedPercent' => 100.0 - $freeMemoryPercent]],
            blocked: $blocked,
            level: $blocked ? 'blocker' : 'ok',
        );

        $runner = new RunnerState(
            $id,
            $id,
            $active,
            memoryInfo: new MemoryInfo($total, $free),
            unixtimeLastHealth: $healthAt ?? time(),
            resources: $resources,
            publicAddress: $publicAddress,
        );

        return $runner;
    }
}
