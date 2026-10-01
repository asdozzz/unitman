<?php

namespace App\Unitman\Business\Model\Unit;

use App\Unitman\Business\Model\Unit\Runner\RunnerJob;
use App\Unitman\Business\Model\Unit\UnitProcess\UnitProcessState;
use App\Unitman\Business\Model\Unit\UnitProcess\UnitProcessType;

final class UnitProcess
{
    /**
     * @param RunnerJob[] $jobs
     * */
    private function __construct(
        public readonly string $id,
        public readonly string $userId,
        public readonly UnitProcessType $type,
        public UnitProcessState $state,
        public array $jobs
    )
    {
    }

    static function make(string $id, string $userId, UnitProcessType $type): self
    {
        return new self($id, $userId, $type, UnitProcessState::NEW, []);
    }

    /**
     * @param RunnerJob[] $jobs
     * */
    function dobavitZadachiVProzess(array $jobs): void
    {
        $this->state = UnitProcessState::ZADACHI_DOBAVLENI;
        $this->jobs = $jobs;
    }

    function esliEstZadacha(string $jobId): bool
    {
        foreach ($this->jobs as $job) {
            if ($job->getJobId() === $jobId) {
                return true;
            }
        }

        return false;
    }

    function findJob(string $jobId): ?RunnerJob
    {
        $result = null;
        foreach ($this->jobs as $job) {
            if ($job->getJobId() === $jobId) {
                $result = $job;
            }
        }

        return $result;
    }

    function startJob(string $jobId): void
    {
        foreach ($this->jobs as $job) {
            if ($job->getJobId() === $jobId) {
                $job->start();
            }
        }
        $this->state = UnitProcessState::PENDING;
    }

    function cancelJob(string $jobId, string $message): void
    {
        foreach ($this->jobs as $job) {
            if ($job->getJobId() === $jobId) {
                $job->cancel($message);
            }
        }

        foreach ($this->jobs as $job) {
            if (!$job->isFinish()) {
                $job->cancel();
            }
        }
        $this->state = UnitProcessState::CANCLED;
    }

    function isFinish(): bool
    {
        return in_array($this->state,[UnitProcessState::ERROR, UnitProcessState::SUCCESS, UnitProcessState::CANCLED]);
    }

    function ustanovitResultatZadachi(string $jobId, bool $success, array $steps): void
    {
        if ($this->isFinish()) {
            throw new \Exception('unit.process.already_finish');
        }

        foreach ($this->jobs as $job) {
            if ($job->getJobId() === $jobId) {
                $job->ustanovitResultat($success, $steps);
            }
        }
        if (!$success) {
            $this->state = UnitProcessState::ERROR;

            foreach ($this->jobs as $job) {
                if (!$job->isFinish()) {
                    $job->cancel();
                }
            }
        } else {
            $issetNotFinished = false;

            foreach ($this->jobs as $job) {
                if (!$job->isFinish()) {
                    $issetNotFinished = true;
                }
            }

            if (!$issetNotFinished) {
                $this->state = UnitProcessState::SUCCESS;
            }
        }
    }

    function toArray(): array
    {
        return [
            'id' => $this->id,
            'userId' => $this->userId,
            'type' => $this->type->value,
            'state' => $this->state->value,
            'jobs' => array_map(fn(RunnerJob $job) => $job->toArray(), $this->jobs)
        ];
    }

    static function fromArray(array $data): self
    {
        $jobs = [];
        foreach ($data['jobs'] as $jobArr) {
            $jobs[] = RunnerJob::fromArray($jobArr);
        }
        $state = UnitProcessState::from($data['state']);
        $type = UnitProcessType::from($data['type']);
        return new self($data['id'],$data['userId'], $type, $state, $jobs);
    }

    function getLastUnixtime(): int|null
    {
        $res = null;

        foreach ($this->jobs as $job) {
            $jobUnixtime = $job->getLastUnixtime();
            if (!empty($jobUnixtime)) {
                $res = $jobUnixtime;
            }
        }

        return $res;
    }
}
