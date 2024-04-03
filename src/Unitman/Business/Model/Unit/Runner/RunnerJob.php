<?php

namespace App\Unitman\Business\Model\Unit\Runner;

final class RunnerJob
{
    private string $jobId;
    private bool $isFinish;
    private bool $success;
    /**
     * @var array<RunnerJobStep>
     * */
    private array $steps;

    /**
     * @param array<RunnerJobStep> $steps
     * */
    public function __construct(string $jobId, bool $isFinish = false, bool $success = false, array $steps = [])
    {
        if (empty($jobId)) {
            throw new \DomainException('unit.sborka.jobId_is_empty');
        }
        $this->jobId = $jobId;
        $this->isFinish = $isFinish;
        $this->success = $success;
        $this->steps = $steps;
    }

    static function start(string $jobId): static
    {
        return new static($jobId);
    }

    function ustanovitUspeh(array $steps): static
    {
        return new static($this->jobId, true, true, $steps);
    }

    function ustanovitOshibku(array $steps): static
    {
        return new static($this->jobId, true, false, $steps);
    }

    /**
     * @return string
     */
    public function getJobId(): string
    {
        return $this->jobId;
    }

    /**
     * @return bool
     */
    public function isFinish(): bool
    {
        return $this->isFinish;
    }

    /**
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * @return string
     */
    public function getSteps(): array
    {
        return $this->steps;
    }


}
