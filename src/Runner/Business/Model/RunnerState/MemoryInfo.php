<?php

namespace App\Runner\Business\Model\RunnerState;

readonly class MemoryInfo
{
    public function __construct(
        public int $totalAsKb,
        public int $freeAsKb,
    )
    {
    }

    function getFreePercent(): float
    {
        if (empty($this->totalAsKb)) return 0;
        $percent = $this->freeAsKb * 100 / $this->totalAsKb;
        return round($percent, 2);
    }
}
