<?php

namespace App\Unitman\Business\Model\Unit\Runner;

final class RunnerJobStep
{
    /**
     * `$unixtime` — время окончания шага, `$unixtimeStart` — необязательное время
     * начала. Параметр добавлен последним с дефолтом `null`, чтобы события,
     * сохранённые до его появления, продолжали десериализоваться без ошибок.
     */
    public function __construct(
        public readonly string $command,
        public readonly string $response,
        public readonly bool $success,
        public readonly int $unixtime,
        public readonly ?int $unixtimeStart = null)
    {
    }

    /**
     * Ключ `unixtimeStart` эмитится только при наличии значения — так payload
     * старых шагов остаётся идентичным прежнему (event store, проекции, API).
     *
     * @return array{command: string, response: string, success: bool, unixtime: int, unixtimeStart?: int}
     */
    function toArray(): array
    {
        $result = [
            'command' => $this->command,
            'response' => $this->response,
            'success' => $this->success,
            'unixtime' => $this->unixtime
        ];

        if ($this->unixtimeStart !== null) {
            $result['unixtimeStart'] = $this->unixtimeStart;
        }

        return $result;
    }

    /**
     * Отсутствие ключа (шаги из старых событий) трактуется как `null`.
     *
     * @param array<array-key, mixed> $data
     */
    static function fromArray(array $data): self
    {
        $unixtimeStart = $data['unixtimeStart'] ?? null;

        return new self(
            $data['command'],
            $data['response'],
            $data['success'],
            $data['unixtime'],
            is_int($unixtimeStart) ? $unixtimeStart : null,
        );
    }
}
