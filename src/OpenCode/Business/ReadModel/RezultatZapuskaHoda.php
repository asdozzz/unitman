<?php

declare(strict_types=1);

namespace App\OpenCode\Business\ReadModel;

/**
 * Идентификаторы, которые получает клиент после старта хода.
 *
 * `progressChannel` — имя Centrifugo-канала для подписки на шаги хода. Оно
 * вычисляется Runner'ом из workflowId и передаётся сюда, чтобы клиент мог
 * подписаться сразу после старта, не дожидаясь первого `saveSteps` и не зная
 * внутреннего формата имени канала.
 */
final class RezultatZapuskaHoda
{
    public function __construct(
        public readonly string $turnId,
        public readonly string $status,
        public readonly string $progressChannel,
    ) {
    }

    /**
     * @return array{turnId: string, status: string, progressChannel: string}
     */
    public function toArray(): array
    {
        return [
            'turnId' => $this->turnId,
            'status' => $this->status,
            'progressChannel' => $this->progressChannel,
        ];
    }
}