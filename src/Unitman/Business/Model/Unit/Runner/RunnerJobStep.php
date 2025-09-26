<?php

namespace App\Unitman\Business\Model\Unit\Runner;

final class RunnerJobStep
{
    public function __construct(
        public readonly string $command,
        public readonly string $response,
        public readonly bool $success,
        public readonly int $unixtime)
    {
    }

    function toArray(): array
    {
        return [
            'command' => $this->command,
            'response' => $this->response,
            'success' => $this->success,
            'unixtime' => $this->unixtime
        ];
    }

    static function fromArray(array $data): self
    {
        return new self($data['command'], $data['response'], $data['success'], $data['unixtime']);
    }
}
