<?php

declare(strict_types=1);

namespace App\AiChat\Business\Port;

/**
 * Генератор UUID для сущностей AI-чата.
 *
 * Отделён от инфраструктуры, чтобы use case мог получать детерминированные
 * идентификаторы в тестах и не зависеть от конкретной библиотеки.
 */
interface UuidGenerator
{
    /**
     * Сгенерировать новый UUID.
     *
     * @return non-empty-string
     */
    public function generit(): string;
}
