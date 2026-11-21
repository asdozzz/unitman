<?php

declare(strict_types=1);

namespace App\Unitman\Business\Model\Unit\Event;

/**
 * Пользователь прикреплён к юниту как редактор.
 *
 * Хранится только `userId`: подходящий личный токен разрешается в момент
 * использования.
 */
final class RedaktorPrikreplenKUnitu
{
    public function __construct(
        public readonly string $unitId,
        public readonly string $userId,
        public readonly int $unixtime,
    )
    {
    }
}
