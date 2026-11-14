<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Model;

/**
 * Снапшот прогресса хода, приведённый к виду, безопасному для отдачи во фронтенд.
 *
 * Шаги приходят от раннера как есть (ключи Command/Response/Success/Unixtime):
 * модуль OpenCode их не интерпретирует, только передаёт дальше.
 */
final class ProgressHodaOpenCode
{
    /**
     * @param list<array<array-key, mixed>> $steps
     */
    public function __construct(
        public readonly array $steps,
        public readonly bool $finished,
        public readonly bool $success,
        public readonly int $updatedAt,
    ) {
    }

    /**
     * @return array{
     *     steps: list<array<array-key, mixed>>,
     *     finished: bool,
     *     success: bool,
     *     updatedAt: int
     * }
     */
    public function toArray(): array
    {
        return [
            'steps' => $this->steps,
            'finished' => $this->finished,
            'success' => $this->success,
            'updatedAt' => $this->updatedAt,
        ];
    }
}