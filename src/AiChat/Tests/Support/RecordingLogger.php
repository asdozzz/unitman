<?php

declare(strict_types=1);

namespace App\AiChat\Tests\Support;

use Psr\Log\AbstractLogger;

/**
 * Логгер для тестов: запоминает уровень, сообщение и контекст.
 *
 * Позволяет проверить, что при 4xx/5xx в лог попадают только статус,
 * провайдер и поля ошибки upstream, без ключей, заголовков и переписки.
 */
final class RecordingLogger extends AbstractLogger
{
    /**
     * @var list<array{level: mixed, message: string|\Stringable, context: array<array-key, mixed>}>
     */
    public array $records = [];

    /**
     * @param array<array-key, mixed> $context
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];
    }
}
