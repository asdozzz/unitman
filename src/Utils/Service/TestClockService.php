<?php

namespace App\Utils\Service;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

final class TestClockService implements ClockInterface
{
    private DateTimeImmutable $now;

    public function __construct(\DateTimeImmutable|null $now = null)
    {
        $this->now = $now ?? new DateTimeImmutable();
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    function setNow(DateTimeImmutable $newNow): void
    {
        $this->now = $newNow;
    }
}
