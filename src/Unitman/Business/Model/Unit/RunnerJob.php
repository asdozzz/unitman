<?php

namespace App\Unitman\Business\Model\Unit;

final class RunnerJob
{
    private string $jobId;
    private bool $isFinish;
    private bool $success;
    private string $textOtRunnera;

    public function __construct(string $jobId, bool $isFinish = false, bool $success = false, string $textOtRunnera = '')
    {
        if (empty($jobId)) {
            throw new \DomainException('unit.sborka.jobId_is_empty');
        }
        $this->jobId = $jobId;
        $this->isFinish = $isFinish;
        $this->success = $success;
        $this->textOtRunnera = $textOtRunnera;
    }

    static function start(string $jobId): static
    {
        return new static($jobId);
    }

    function ustanovitUspeh(string $textOtRunnera): static
    {
        return new static($this->jobId, true, true, $textOtRunnera);
    }

    function ustanovitOshibku(string $textOtRunnera): static
    {
        return new static($this->jobId, true, false, $textOtRunnera);
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
    public function getTextOtRunnera(): string
    {
        return $this->textOtRunnera;
    }


}
