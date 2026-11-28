<?php

declare(strict_types=1);

namespace App\Skill\Business\Port;

/**
 * Генератор идентификаторов навыков.
 *
 * Вынесен в порт, чтобы UseCase можно было тестировать детерминированно.
 */
interface UuidGenerator
{
    public function makeGuid(): string;
}
