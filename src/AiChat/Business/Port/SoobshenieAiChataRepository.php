<?php

declare(strict_types=1);

namespace App\AiChat\Business\Port;

use App\AiChat\Business\Model\SoobshenieAiChata;

/**
 * Хранилище истории сообщений AI-чата.
 *
 * Сообщения неизменяемы: допускается только добавление и удаление всей
 * истории сессии. Порядок определяется колонкой `seq`.
 */
interface SoobshenieAiChataRepository
{
    /**
     * Добавить одно или несколько сообщений одной транзакцией.
     */
    public function dobavit(SoobshenieAiChata ...$soobsheniya): void;

    /**
     * Последние `$limit` сообщений сессии в возрастающем порядке `seq`.
     *
     * @return list<SoobshenieAiChata>
     */
    public function poslednie(string $sessionId, int $limit): array;

    /**
     * Все сообщения сессии по возрастанию `seq`.
     *
     * @return list<SoobshenieAiChata>
     */
    public function vse(string $sessionId): array;

    public function kolichestvo(string $sessionId): int;

    /**
     * Первое сообщение сессии (минимальный `seq`).
     */
    public function pervoe(string $sessionId): ?SoobshenieAiChata;

    public function udalitPoSessii(string $sessionId): void;
}
