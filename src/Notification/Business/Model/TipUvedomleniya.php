<?php

declare(strict_types=1);

namespace App\Notification\Business\Model;

/**
 * Тип уведомления.
 *
 * Системные типы нельзя отключить в настройках: они создаются всегда.
 * `runner_threat` относится к системным, `unit_started` — к отключаемым.
 */
enum TipUvedomleniya: string
{
    case RUNNER_THREAT = 'runner_threat';
    case UNIT_STARTED = 'unit_started';

    public function sistemniy(): bool
    {
        return $this === self::RUNNER_THREAT;
    }

    public function dlyaAdministratorov(): bool
    {
        return $this === self::RUNNER_THREAT;
    }

    public function vazhnostPoUmolchaniyu(): UrovenVazhnosti
    {
        return match ($this) {
            self::RUNNER_THREAT => UrovenVazhnosti::CRITICAL,
            self::UNIT_STARTED => UrovenVazhnosti::INFO,
        };
    }

    /**
     * Все типы, доступные для управления в настройках.
     *
     * @return list<self>
     */
    public static function vse(): array
    {
        return [self::RUNNER_THREAT, self::UNIT_STARTED];
    }
}
