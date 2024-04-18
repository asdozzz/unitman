<?php

namespace App\Runner\Business\Model;

final class RunnerState implements \JsonSerializable
{

    public function __construct(private string $id, private string $taskQueue, private bool $active)
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

    function ustanovitResultatRabotosposobnosti(bool $active): void
    {
        $this->active = $active;
    }

    function getId():string
    {
        return $this->id;
    }

    public function jsonSerialize(): mixed
    {
        return get_object_vars($this);
    }
}
