<?php

declare(strict_types=1);

namespace App\AiChat\Business\Model;

/**
 * Роль автора сообщения в AI-чате.
 *
 * Совпадает с ролями OpenAI chat completions: `system`, `user`, `assistant`
 * и `tool` (результат вызова инструмента).
 */
enum RolSoobsheniyaAiChata: string
{
    case System = 'system';
    case User = 'user';
    case Assistant = 'assistant';
    case Tool = 'tool';
}
