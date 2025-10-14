<?php

namespace App\Unitman\Business\Model\Unit\Runner;

final class RunnerJob
{
    private string $jobId;
    /**
     * @var array<RunnerJobStep>
     * */
    private array $steps;
    private RunnerJobState $state;
    private RunnerJobType $type;

    /**
     * @param array<RunnerJobStep> $steps
     * */
    public function __construct(
        string $jobId,
        RunnerJobType $type,
        RunnerJobState $state,
        array $steps = [],
    )
    {

        if (empty($jobId)) {
            throw new \DomainException('unit.sborka.jobId_is_empty');
        }
        $this->jobId = $jobId;
        $this->steps = $steps;
        $this->state = $state;
        $this->type = $type;
    }

    static function make(string $jobId, RunnerJobType $type): self
    {
        return new self($jobId, $type, RunnerJobState::NEW, []);
    }

    function getType(): RunnerJobType
    {
        return $this->type;
    }

    function start(): void
    {
        $this->state = RunnerJobState::PENDING;
    }

    function cancel(string $message = 'CANCEL'): void
    {
        $this->state = RunnerJobState::CANCLED;
        $this->steps = [(new RunnerJobStep('CANCEL', $message, false, time()))];
    }

    function ustanovitResultat(bool $success, array $steps): void
    {
        if ($success) {
            $this->state = RunnerJobState::SUCCESS;
        } else {
            $this->state = RunnerJobState::ERROR;
        }

        $this->steps = $steps;
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }

    public function isNew(): bool
    {
        return $this->state === RunnerJobState::NEW;
    }

    public function isStart(): bool
    {
        return $this->state === RunnerJobState::PENDING;
    }

    public function isFinish(): bool
    {
        return in_array($this->state, [RunnerJobState::SUCCESS, RunnerJobState::ERROR, RunnerJobState::CANCLED]);
    }

    public function isSuccess(): bool
    {
        return in_array($this->state, [RunnerJobState::SUCCESS]);
    }

    public function getSteps(): array
    {
        return $this->steps;
    }


    function toArray(): array
    {
        return [
            'id' => $this->jobId,
            'type' => $this->type->value,
            'state' => $this->state->value,
            'steps' => array_map(fn(RunnerJobStep $step) => $step->toArray(),$this->steps),
        ];
    }

    static function fromArray(array $data): static
    {
        $steps = [];
        foreach ($data['steps'] as $stepArr) {
            $steps[] = RunnerJobStep::fromArray($stepArr);
        }
        $state = RunnerJobState::from($data['state']);
        $type = RunnerJobType::from($data['type']);
        return new self($data['id'], $type, $state, $steps);
    }
}
