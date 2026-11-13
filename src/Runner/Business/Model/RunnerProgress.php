<?php

declare(strict_types=1);

namespace App\Runner\Business\Model;

/**
 * Снапшот прогресса выполнения задачи раннера.
 *
 * Шаги хранятся в том же виде, в котором их присылает Golang-раннер
 * (ключи Command/Response/Success/Unixtime) — это сохраняет совместимость
 * с уже существующим контрактом `saveSteps` и десериализацией в `Step`.
 */
final class RunnerProgress
{
    /**
     * @param list<array<array-key, mixed>> $steps
     */
    public function __construct(
        public readonly string $token,
        public readonly array $steps,
        public readonly bool $finished,
        public readonly bool $success,
        public readonly int $unixtime,
    ) {
    }

    /**
     * Сериализация для хранения в KV.
     *
     * @return array{
     *     token: string,
     *     steps: list<array<array-key, mixed>>,
     *     finished: bool,
     *     success: bool,
     *     unixtime: int
     * }
     */
    public function toArray(): array
    {
        return [
            'token' => $this->token,
            'steps' => $this->steps,
            'finished' => $this->finished,
            'success' => $this->success,
            'unixtime' => $this->unixtime,
        ];
    }

    /**
     * Payload для клиента: одинаковый для push-канала Centrifugo и pull-endpoint.
     *
     * @return array{
     *     jobId: string,
     *     steps: list<array<array-key, mixed>>,
     *     finished: bool,
     *     success: bool,
     *     updatedAt: int
     * }
     */
    public function toClientPayload(): array
    {
        return [
            'jobId' => $this->token,
            'steps' => $this->steps,
            'finished' => $this->finished,
            'success' => $this->success,
            'updatedAt' => $this->unixtime,
        ];
    }
}
