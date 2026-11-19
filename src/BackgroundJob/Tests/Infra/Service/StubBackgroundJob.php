<?php

declare(strict_types=1);

namespace App\BackgroundJob\Tests\Infra\Service;

use App\BackgroundJob\Infra\Service\BackgroundJobGroup;
use App\BackgroundJob\Infra\Service\BackgroundJobInterface;

/**
 * Управляемая задача для тестов планировщика.
 */
final class StubBackgroundJob implements BackgroundJobInterface
{
    public int $runCount = 0;

    public function __construct(
        private string $name,
        private BackgroundJobGroup $group,
        private int $delay = 1,
        private bool|\Throwable|null $result = null,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getGroup(): BackgroundJobGroup
    {
        return $this->group;
    }

    public function getDelay(): int
    {
        return $this->delay;
    }

    public function getProcessNum(): int
    {
        return 1;
    }

    public function run(): bool
    {
        ++$this->runCount;

        if ($this->result instanceof \Throwable) {
            throw $this->result;
        }

        return $this->result ?? true;
    }
}
