<?php

declare(strict_types=1);

namespace App\BackgroundJob\Tests\Infra\Service;

use App\BackgroundJob\Infra\Service\BackgroundJobGroup;
use App\BackgroundJob\Infra\Service\BackgroundJobScheduler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class BackgroundJobSchedulerTest extends TestCase
{
    #[Test]
    public function tick_runs_only_jobs_of_requested_group(): void
    {
        $projection = new StubBackgroundJob('projection_job', BackgroundJobGroup::projections, 0);
        $producer = new StubBackgroundJob('producer_job', BackgroundJobGroup::producers, 0);
        $scheduler = $this->scheduler([$projection, $producer]);

        $scheduler->tick(BackgroundJobGroup::projections);

        self::assertSame(1, $projection->runCount);
        self::assertSame(0, $producer->runCount);
    }

    #[Test]
    public function job_is_not_run_until_delay_elapsed(): void
    {
        $job = new StubBackgroundJob('slow_job', BackgroundJobGroup::timers, 3600);
        $scheduler = $this->scheduler([$job]);

        $scheduler->tick(BackgroundJobGroup::timers);
        $scheduler->tick(BackgroundJobGroup::timers);

        self::assertSame(1, $job->runCount);
    }

    #[Test]
    public function job_runs_again_when_delay_elapsed(): void
    {
        $job = new StubBackgroundJob('fast_job', BackgroundJobGroup::timers, 0);
        $scheduler = $this->scheduler([$job]);

        $scheduler->tick(BackgroundJobGroup::timers);
        $scheduler->tick(BackgroundJobGroup::timers);

        self::assertSame(2, $job->runCount);
    }

    #[Test]
    public function throwable_is_isolated_and_other_jobs_continue(): void
    {
        $broken = new StubBackgroundJob(
            'broken_job',
            BackgroundJobGroup::timers,
            0,
            new \RuntimeException('boom'),
        );
        $healthy = new StubBackgroundJob('healthy_job', BackgroundJobGroup::timers, 0);
        $scheduler = $this->scheduler([$broken, $healthy]);

        $scheduler->tick(BackgroundJobGroup::timers);

        self::assertSame(1, $broken->runCount);
        self::assertSame(1, $healthy->runCount);
    }

    #[Test]
    public function false_result_is_isolated_and_other_jobs_continue(): void
    {
        $broken = new StubBackgroundJob('false_job', BackgroundJobGroup::timers, 0, false);
        $healthy = new StubBackgroundJob('healthy_job', BackgroundJobGroup::timers, 0);
        $scheduler = $this->scheduler([$broken, $healthy]);

        $scheduler->tick(BackgroundJobGroup::timers);

        self::assertSame(1, $broken->runCount);
        self::assertSame(1, $healthy->runCount);
    }

    #[Test]
    public function job_is_paused_after_three_consecutive_failures(): void
    {
        $job = new StubBackgroundJob(
            'failing_job',
            BackgroundJobGroup::timers,
            0,
            new \RuntimeException('boom'),
        );
        $scheduler = $this->scheduler([$job]);

        $scheduler->tick(BackgroundJobGroup::timers);
        $scheduler->tick(BackgroundJobGroup::timers);
        $scheduler->tick(BackgroundJobGroup::timers);
        $scheduler->tick(BackgroundJobGroup::timers);

        self::assertSame(3, $job->runCount);
    }

    #[Test]
    public function unknown_group_is_rejected(): void
    {
        self::assertFalse(BackgroundJobGroup::isValid('unknown'));
        self::assertTrue(BackgroundJobGroup::isValid('projections'));
        self::assertSame(
            ['projections', 'producers', 'timers'],
            BackgroundJobGroup::all(),
        );
    }

    /**
     * @param list<StubBackgroundJob> $jobs
     */
    private function scheduler(array $jobs): BackgroundJobScheduler
    {
        return new BackgroundJobScheduler($jobs, new NullLogger());
    }
}
