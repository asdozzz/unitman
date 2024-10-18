<?php

namespace App\Runner\Business\Model;

use App\Runner\Business\Model\RunnerState\DockerContainerStats;
use App\Runner\Business\Model\RunnerState\MemoryInfo;

final class RunnerState implements \JsonSerializable
{
    /**
     * @param DockerContainerStats[]
     * */
    public function __construct(
        private string $id,
        private string $taskQueue,
        private bool $active,
        private array $dockerStats = [],
        private MemoryInfo $memoryInfo = new MemoryInfo(0, 0),
    )
    {
    }

    static function sozdatDefoltniiRunner(string $id): self
    {
        return new self($id, 'unitman-runner-queue', false);
    }

    function getTaskQueue(): string
    {
        return $this->taskQueue;
    }

    function isActive(): bool
    {
        return $this->active;
    }

    function ustanovitResultatRabotosposobnosti(bool $active, array $dockerStats, MemoryInfo $memoryInfo): void
    {
        $this->active = $active;
        $this->dockerStats = $dockerStats;
        $this->memoryInfo = $memoryInfo;
    }

    function getId():string
    {
        return $this->id;
    }

    public function getDockerStats(): array
    {
        return $this->dockerStats;
    }

    public function getMemoryInfo(): MemoryInfo
    {
        return $this->memoryInfo;
    }

    public function jsonSerialize(): mixed
    {
        return get_object_vars($this);
    }
}
