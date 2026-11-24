<?php

declare(strict_types=1);

namespace App\Runner\Business\Utils;

use Ramsey\Uuid\Uuid;

/**
 * Генератор идентификатора раннера.
 *
 * Контракт `plans/runner-multi-runner.md` §4 требует строго UUID v4:
 * `runnerId` одновременно является именем Temporal task queue, поэтому
 * версия и канонический вид (lowercase, 36 символов) фиксированы.
 *
 * Существующий порт `CanGenerateGuid` намеренно не используется: его
 * реализация выдаёт UUID v7 (см. `AbstractRamseyGuidGenerator`), что нарушает
 * контракт multi-runner.
 */
final class RunnerIdGenerator
{
    public function generate(): string
    {
        return Uuid::uuid4()->toString();
    }
}
