<?php

declare(strict_types=1);

namespace App\BackgroundJob\Infra\Service;

/**
 * Группа фоновых задач, которую обслуживает один процесс-планировщик.
 */
enum BackgroundJobGroup: string
{
    case projections = 'projections';
    case producers = 'producers';
    case timers = 'timers';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_map(
            static fn (self $group): string => $group->value,
            self::cases(),
        );
    }

    public static function isValid(string $value): bool
    {
        return null !== self::tryFrom($value);
    }
}
