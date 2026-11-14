<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Model;

/**
 * Состояние хода диалога с OpenCode.
 *
 * Ход живёт до того момента, пока раннер не вернёт результат:
 * - RUNNING        — активити ещё выполняется, результата нет;
 * - AWAITING_INPUT — раннер задал вопрос и ждёт выбора пользователя;
 * - ANSWERED       — получен финальный ответ, ход закрыт;
 * - FAILED         — ошибка выполнения или недоступность раннера.
 */
enum StatusHoda: string
{
    case RUNNING = 'RUNNING';
    case AWAITING_INPUT = 'AWAITING_INPUT';
    case ANSWERED = 'ANSWERED';
    case FAILED = 'FAILED';

    public function zavershen(): bool
    {
        return $this !== self::RUNNING;
    }

    public function trebuetOtveta(): bool
    {
        return $this === self::AWAITING_INPUT;
    }

    public static function poStrokoi(string $status): self
    {
        return self::tryFrom($status) ?? throw new \DomainException('opencode.turn.invalid_status');
    }
}